<?php

namespace App\MessageHandler;

use App\Entity\Game;
use App\Entity\Player;
use App\Message\GameMessage;
use App\Message\PlayerMessage;
use App\Repository\GameRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Workflow\WorkflowInterface;

#[AsMessageHandler]
class GameMessageHandler
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private GameRepository         $gameRepository,
        private WorkflowInterface      $gameStateMachine,
        private MessageBusInterface    $bus,
    )
    {
    }

    public function __invoke(GameMessage $message)
    {
        // get entity
        $game = $this->gameRepository->find($message->getId());

        // initialize new messages to deliver
        $new_messages = [];
        switch ($game->getState()) {
            case Game::STATE_NEW:
                $new_messages = $this->atNew($game);
                break;
            case Game::STATE_PLAYING:
                $new_messages = $this->atPlaying($game);
                break;
            case Game::STATE_PLAYERS_FINISHED:
                $this->atPlayersFinished($game);
                break;
            default:
        }

        // persist entity
        $this->entityManager->persist($game);
        $this->entityManager->flush();

        // deliver messages
        array_walk($new_messages, fn($x) => $this->bus->dispatch($x));
    }

    /**
     * New game started, transition to playing and throw a new game message to select first player
     *
     * @param Game $game
     * @return array
     */
    private function atNew(Game $game): array
    {
        // start game
        $this->gameStateMachine->apply($game, 'start');

        // dispatch new game message, to select first player
        return [new GameMessage($game->getId())];
    }

    /**
     * A player has finished his round, check if there are waiting players and select the next one
     *
     * @param Game $game
     * @return array
     */
    private function atPlaying(Game $game): array
    {
        $out_messages = [];
        // Get waiting players
        /** @var Player[] $players */
        $players = $game->getPlayers()->filter(function (Player $player) {
            return $player->getState() == Player::STATE_WAITING;
        })->toArray();

        if (count($players) > 0) {
            // order players based on last played round and position on game
            usort($players, function (Player $a, Player $b) {
                if ($a->getLastRound() == $b->getLastRound()) {
                    return $a->getPosition() - $b->getPosition();
                }

                return $a->getLastRound() - $b->getLastRound();
            });

            // If there are players waiting
            // throw that player to the court!
            $out_messages[] = new PlayerMessage($players[0]->getId());
        } else {
            // end game
            $this->gameStateMachine->apply($game, 'end');
            $out_messages[] = new GameMessage($game->getId());

        }

        return $out_messages;
    }

    /**
     * After the game has finished, select the winner
     *
     * @param Game $game
     * @return void
     */
    private function atPlayersFinished(Game $game): void
    {
        // get players
        $players = $game->getPlayers();
        // initialize empty winner
        $winner = new Player();

        // check which player has won
        foreach ($players as $player) {
            if ($player->getFinalScore() > $winner->getFinalScore()) {
                $winner = $player;
            }
        }

        // if there are a winner, set into game
        if ($winner->getId() != null) {
            $game->setWinnerPlayer($winner);
        }

        $this->gameStateMachine->apply($game, 'check_winner');
    }
}

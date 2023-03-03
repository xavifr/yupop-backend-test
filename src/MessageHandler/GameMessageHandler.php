<?php

namespace App\MessageHandler;

use App\Entity\Game;
use App\Entity\Player;
use App\Message\GameMessage;
use App\Message\PlayerMessage;
use App\Repository\FrameRepository;
use App\Repository\GameRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\Handler\MessageHandlerInterface;
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
            case 'new':
                $new_messages = $this->atNew($game);
                break;
            case 'playing':
                $new_messages = $this->atPlaying($game);
                break;
            case 'players_finished':
                $this->atPlayersFinished($game);
                break;
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
     * @return iterable
     */
    private function atNew(Game $game): iterable
    {
        // start game
        $this->gameStateMachine->apply($game, 'start');

        // dispatch new game message, to select first player
        yield new GameMessage($game->getId());
    }

    /**
     * A player has finished his round, check if there are waiting players and select the next one
     *
     * @param Game $game
     * @return iterable
     */
    private function atPlaying(Game $game): iterable
    {
        // Get waiting players
        /** @var Player[] $players */
        $players = $game->getPlayers()->filter(function (Player $player) {
            return $player->getState() == 'waiting';
        })->toArray();

        // order players based on last played round and position on game
        usort($players, function (Player $a, Player $b) {
            if ($a->getLastRound() == $b->getLastRound()) {
                return $a->getPosition() - $b->getPosition();
            } else {
                return $a->getLastRound() - $b->getLastRound();
            }
        });

        // If there are players waiting
        if (count($players) > 0) {
            // throw that player to the court!
            yield new PlayerMessage($players[0]->getId());
        } else {
            // end game
            $this->gameStateMachine->apply($game, 'end');
        }
    }

    /**
     * After the game has finished, select the winner
     *
     * @param Game $game
     * @return void
     */
    private function atPlayersFinished(Game $game): void {
        // get players
        $players = $game->getPlayers();
        // initialize empty winner
        $winner = new Player();

        // check which player has won
        foreach($players as $player) {
            if ($player->getFinalScore() > $winner->getFinalScore()) {
                $winner = $player;
            }
        }

        // if there are a winner, set into game
        if ($winner->getId() != null) {
            $game->setWinnerPlayer($winner);
        }
    }
}

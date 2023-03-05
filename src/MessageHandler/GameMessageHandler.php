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
        private ?LoggerInterface       $logger,

    )
    {
    }

    public function __invoke(GameMessage $message)
    {
        // get entity
        $game = $this->gameRepository->find($message->getId());

        $this->logger->error(sprintf("Received message for game %s in state '%s'", $game->getReference(), $game->getState()));

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
            default:
                $this->logger->error("unknown state");
        }

        $this->logger->error(" New state is " . $game->getState());
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
        $this->logger->error("  New state is " . $game->getState());

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
            return $player->getState() == 'waiting';
        })->toArray();

        $this->logger->error(sprintf("  found %d players waiting...", count($players)));

        if (count($players) > 0) {
            // order players based on last played round and position on game
            usort($players, function (Player $a, Player $b) {
                if ($a->getLastRound() == $b->getLastRound()) {
                    return $a->getPosition() - $b->getPosition();
                } else {
                    return $a->getLastRound() - $b->getLastRound();
                }
            });

            $this->logger->error(sprintf("  players, sorted by position, are: %s", print_r($players, true)));

            // If there are players waiting
            $this->logger->error(sprintf("  selected player %s", $players[0]->getName()));

            // throw that player to the court!
            $out_messages[] = new PlayerMessage($players[0]->getId());
        } else {
            $this->logger->error(sprintf("  no players waiting... end game"));

            // end game
            $this->gameStateMachine->apply($game, 'end');
        }

        $this->logger->error("  New state is " . $game->getState());

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

        $this->logger->error(sprintf("  looking for a winner, in a %d players array", count($players)));


        // check which player has won
        foreach ($players as $player) {
            if ($player->getFinalScore() > $winner->getFinalScore()) {
                $winner = $player;
            }
        }

        // if there are a winner, set into game
        if ($winner->getId() != null) {
            $this->logger->error(sprintf("  and the winner is... %s!!", $winner->getName()));

            $game->setWinnerPlayer($winner);
        }
    }
}

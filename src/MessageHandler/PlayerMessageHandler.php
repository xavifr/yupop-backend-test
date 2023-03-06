<?php

namespace App\MessageHandler;

use App\Entity\Frame;
use App\Entity\Game;
use App\Entity\Player;
use App\Message\GameMessage;
use App\Message\PlayerMessage;
use App\Repository\PlayerRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Workflow\WorkflowInterface;

#[AsMessageHandler]
class PlayerMessageHandler
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private PlayerRepository       $playerRepository,
        private WorkflowInterface      $playerStateMachine,
        private MessageBusInterface    $bus,
        private ?LoggerInterface $logger,
    )
    {
    }

    public function __invoke(PlayerMessage $message)
    {
        // get entity
        $player = $this->playerRepository->find($message->getId());

        $this->logger->error(sprintf("Received message for player %s in state '%s'", $player->getName(), $player->getState()));

        // initialize new messages to deliver
        $new_messages = [];

        switch ($player->getState()) {
            case 'waiting':
                $this->atWaiting($player);
                break;
            case 'playing':
                $new_messages = $this->atPlaying($player, $message->getNextRound());
                break;
        }

        $this->logger->error(sprintf(" PLAYER: New state is %s" , $player->getState()));


        // persist entity
        $this->entityManager->persist($player);
        $this->entityManager->flush();

        // deliver messages
        array_walk($new_messages, fn($x) => $this->bus->dispatch($x));
    }

    /**
     * The player is waiting for his turn, transition to playing
     *
     * @param Player $player
     * @return void
     */
    private function atWaiting(Player $player): void
    {
        if (count($player->getFrames()) == 0) {
            $this->logger->error(" PLAYER creating the first frame");

            $new_frame = new Frame();
            $new_frame->setPlayer($player);
            $new_frame->setRound(1);
            $player->addFrame($new_frame);
            $this->entityManager->persist($new_frame);
        }

        // start_frame to transition to playing
        $this->playerStateMachine->apply($player, 'start_frame');
    }

    /**
     * Player probably finished his frame,
     *  prepare next round or finish his game
     *
     * @param Player $player
     * @param int $next_round
     * @return array
     */
    private function atPlaying(Player $player, int $next_round): array
    {
        $out_messages = [];

        $this->logger->error(" PLAYER finished his turn");


        // calc player final score
        $final_score = 0;
        $done_frames = $player->getFrames()->filter(function (Frame $frame) {
            return $frame->getState() == 'done';
        });
        foreach ($done_frames as $frame) {
            $final_score += $frame->getScore();
        }

        $this->logger->error(sprintf(" PLAYER: updated score to %d", $final_score));
        $player->setFinalScore($final_score);
        $player->setLastRound($player->getFrames()->last()->getRound());


        if ($next_round == 0) {
            $this->logger->error(sprintf(" PLAYER: this was the last round, end game"));

            // end game for this player
            $this->playerStateMachine->apply($player, 'end_game');

            $out_messages[] = new GameMessage($player->getGame()->getId());
        } else {

            $this->logger->error(sprintf(" PLAYER: creating new frame %d", $next_round));

            // create a new frame for next round
            $new_frame = new Frame();
            $new_frame->setPlayer($player);
            $new_frame->setRound($next_round);
            $player->addFrame($new_frame);
            $this->entityManager->persist($new_frame);

            // if not creating at bonus frame
            if ($next_round <= Game::FRAMES_PER_GAME) {
                // transition player to waiting state
                $this->playerStateMachine->apply($player, 'end_frame');

                // force a new player election
                $out_messages[] = new GameMessage($player->getGame()->getId());
            }
        }

        return $out_messages;
    }
}

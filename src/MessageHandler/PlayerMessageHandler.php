<?php

namespace App\MessageHandler;

use App\Entity\Frame;
use App\Entity\Game;
use App\Entity\Player;
use App\Message\GameMessage;
use App\Message\PlayerMessage;
use App\Repository\FrameRepository;
use App\Repository\PlayerRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Config\Definition\Exception\Exception;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Workflow\WorkflowInterface;

#[AsMessageHandler]
class PlayerMessageHandler
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private PlayerRepository       $playerRepository,
        private FrameRepository        $frameRepository,
        private WorkflowInterface      $playerStateMachine,
        private MessageBusInterface    $bus,
    )
    {
    }

    public function __invoke(PlayerMessage $message)
    {
        // get entity
        $player = $this->playerRepository->find($message->getId());

        // check game in correct state
        if ($player->getGame()->getState() != Game::STATE_PLAYING) {
            throw new Exception("Cannot transition a player if the game is not running");
        }

        // initialize new messages to deliver
        $new_messages = [];

        switch ($player->getState()) {
            case Player::STATE_WAITING:
                $this->atWaiting($player);
                break;
            case Player::STATE_PLAYING:
                $new_messages = $this->atPlaying($player);
                break;
        }

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
            // create first frame
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
     * @return array
     */
    private function atPlaying(Player $player): array
    {
        $out_messages = [];

        // calc player final score
        $final_score = 0;
        foreach ($player->getFrames() as $frame) {
            $final_score += $frame->getScore();
        }
        $player->setFinalScore($final_score);

        $player->setLastRound($this->frameRepository->findLastFrameForPlayer($player)->getRound());


        if ($player->getFrames()->count() == Game::FRAMES_PER_GAME) {
            // end game for this player
            $this->playerStateMachine->apply($player, 'end_game');

            $out_messages[] = new GameMessage($player->getGame()->getId());
        } else {
            // create a new frame for next round
            $new_frame = new Frame();
            $new_frame->setPlayer($player);
            $new_frame->setRound($player->getFrames()->count() + 1);
            $player->addFrame($new_frame);
            $this->entityManager->persist($new_frame);

            // transition player to waiting state
            $this->playerStateMachine->apply($player, 'end_frame');

            // force a new player election
            $out_messages[] = new GameMessage($player->getGame()->getId());
        }

        return $out_messages;
    }
}

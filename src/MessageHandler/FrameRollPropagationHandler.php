<?php

namespace App\MessageHandler;

use App\Entity\Frame;
use App\Entity\Game;
use App\Entity\Player;
use App\Message\FrameMessage;
use App\Message\FrameRollPropagationMessage;
use App\Repository\FrameRepository;
use Symfony\Component\Config\Definition\Exception\Exception;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\MessageBusInterface;

#[AsMessageHandler]
class FrameRollPropagationHandler
{
    public function __construct(
        private FrameRepository     $frameRepository,
        private MessageBusInterface $bus,
    )
    {
    }

    /**
     * Try to propagate a roll score from a frame to the previous frames
     * with a pending bonus score.
     *
     * @param FrameRollPropagationMessage $message
     * @return void
     */
    public function __invoke(FrameRollPropagationMessage $message)
    {
        if ($message->getPinsRolled() > Frame::PINS_PER_FRAME) {
            throw new Exception(sprintf("Cannot propagate more than %d pins", Frame::PINS_PER_FRAME));
        }

        // get current frame
        $frame = $this->frameRepository->find($message->getId());

        // check player and game in correct state
        if ($frame->getPlayer()->getGame()->getState() != Game::STATE_PLAYING) {
            throw new Exception("Cannot propagate a score on a frame if the game is not running");
        } else if ($frame->getPlayer()->getState() != Player::STATE_PLAYING) {
            throw new Exception("Cannot propagate a score on a frame if the player is not playing");
        }

        // find valid frames to propagate score
        /** @var Frame[] $frames_to_propagate */
        $frames_to_propagate = $frame->getPlayer()->getFrames()->filter(function (Frame $select_frame) use ($frame) {
            return $select_frame->getRound() < $frame->getRound()
                && $select_frame->getState() == Frame::STATE_WAIT_SCORE
                && $select_frame->getScoreWait() > 0;
        });

        // dispatch message to frames to propagate the score
        foreach ($frames_to_propagate as $frame_to_propagate) {
            $this->bus->dispatch(new FrameMessage($frame_to_propagate->getId(), $message->getPinsRolled()));
        }
    }
}

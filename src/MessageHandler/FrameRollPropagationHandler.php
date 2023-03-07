<?php

namespace App\MessageHandler;

use App\Entity\Frame;
use App\Entity\Game;
use App\Message\FrameMessage;
use App\Message\FrameRollPropagationMessage;
use App\Repository\FrameRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Config\Definition\Exception\Exception;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\Handler\MessageHandlerInterface;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Workflow\WorkflowInterface;

#[AsMessageHandler]
class FrameRollPropagationHandler
{
    public function __construct(
        private FrameRepository        $frameRepository,
        private MessageBusInterface    $bus,
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

        // find valid frames to propagate score
        /** @var Frame[] $frames_to_propagate */
        $frames_to_propagate = $frame->getPlayer()->getFrames()->filter(function (Frame $select_frame) use ($frame) {
            return $select_frame->getId() != $frame->getId()
                && $select_frame->getRound() < $frame->getRound()
                && $select_frame->getState() == Frame::STATE_WAIT_SCORE
                && $select_frame->getScoreWait() > 0;
        });

        // dispatch message to frames to propagate the score
        foreach ($frames_to_propagate as $frame_to_propagate) {
            $this->bus->dispatch(new FrameMessage($frame_to_propagate->getId(), $message->getPinsRolled()));
        }
    }
}

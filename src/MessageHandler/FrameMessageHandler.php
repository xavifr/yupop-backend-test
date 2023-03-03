<?php

namespace App\MessageHandler;

use App\Entity\Frame;
use App\Entity\Game;
use App\Entity\Player;
use App\Message\FrameMessage;
use App\Message\FrameRollPropagation;
use App\Message\PlayerMessage;
use App\Repository\FrameRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\Handler\MessageHandlerInterface;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Workflow\WorkflowInterface;

#[AsMessageHandler]
class FrameMessageHandler
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private FrameRepository        $frameRepository,
        private WorkflowInterface      $frameStateMachine,
        private MessageBusInterface    $bus,
    )
    {
    }

    public function __invoke(FrameMessage $message)
    {
        $frame = $this->frameRepository->find($message->getId());

        $new_messages = [];
        switch ($frame->getState()) {
            case 'new':
                $new_messages = $this->atNew($frame, $message->getPinsRolled());
                break;
            case 'second_stage':
                $new_messages = $this->atSecondStage($frame, $message->getPinsRolled());
                break;
            case 'wait':
                $this->atWait($frame, $message->getPinsRolled());
                break;
        }

        $this->entityManager->persist($frame);
        $this->entityManager->flush();

        array_walk($new_messages, fn($x) => $this->bus->dispatch($x));
    }

    /**
     * Frame is new, so we expect the user to throw his first roll
     *
     * @param Frame $frame
     * @param int $pins_rolled
     * @return iterable
     */
    private function atNew(Frame $frame, int $pins_rolled): iterable
    {
        // do not allow more pins than frame's
        assert($pins_rolled <= Frame::PINS_PER_FRAME);

        // update roll1
        $frame->setRoll1($pins_rolled);

        // if round is FRAMES_PER_GAME+1, player is throwing the bonus ball
        if ($frame->getRound() == Game::FRAMES_PER_GAME + 1) {
            // after ball is thrown, frame is done
            $this->frameStateMachine->apply($frame, 'done');
            yield new PlayerMessage($frame->getPlayer()->getId(), 0);

        } else if ($pins_rolled == Frame::PINS_PER_FRAME) {
            // player scored strike!
            $this->frameStateMachine->apply($frame, 'strike');

            // will have to wait one/two roll propagations
            $frame->setScoreWait(match ($frame->getRound()) {
                Game::FRAMES_PER_GAME => 1,
                default => 2
            });

            yield new PlayerMessage($frame->getPlayer()->getId(), $frame->getRound() + 1);

        } else {
            // wait for second roll
            $this->frameStateMachine->apply($frame, 'second_roll');
        }

        yield new FrameRollPropagation($frame->getId(), $pins_rolled);
    }

    private function atSecondStage(Frame $frame, int $pins_rolled): iterable
    {
        // do not allow more pins than frame's
        assert($frame->getRoll1() + $pins_rolled <= Frame::PINS_PER_FRAME);

        // update roll2
        $frame->setRoll2($pins_rolled);

        if ($frame->getRoll1() + $frame->getRoll2() == Frame::PINS_PER_FRAME) {
            // player scored spare!
            $this->frameStateMachine->apply($frame, 'spare');

            // will have to wait one roll propagations
            $frame->setScoreWait(1);
        } else {
            // frame is done
            $this->frameStateMachine->apply($frame, 'done');
        }
        $next_frame = 0;
        if ($frame->getScore() == Frame::PINS_PER_FRAME || $frame->getRound() < Game::FRAMES_PER_GAME) {
            $next_frame = $frame->getRound()+1;
        }

        // generate new frame or end of game
        yield new PlayerMessage($frame->getPlayer()->getId(), $next_frame);

        // 
        yield new FrameRollPropagation($frame->getId(), $pins_rolled);

        $this->entityManager->flush();

        if ($frame->getState() == 'done') {
            $this->bus->dispatch(new FrameMessage($frame->getId(), 0));
        }
    }


    private function atWait(Frame $frame, int $pins_rolled): void
    {
        // do not allow more pins than frame's
        assert($pins_rolled <= Frame::PINS_PER_FRAME);
        assert($frame->getScoreWait() > 0);

        $frame->setScore($frame->getScore() + $pins_rolled);
        $frame->setScoreWait($frame->getScoreWait() - 1);

        if ($frame->getScoreWait() == 0) {
            $this->frameStateMachine->apply($frame, 'done');
        }
    }

}

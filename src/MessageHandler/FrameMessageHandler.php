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
        // get entity
        $frame = $this->frameRepository->find($message->getId());

        // initialize new messages to deliver
        $new_messages = [];

        switch ($frame->getState()) {
            case 'new':
                $new_messages = $this->atNew($frame, $message->getPinsRolled());
                break;
            case 'second_roll':
                $new_messages = $this->atSecondRoll($frame, $message->getPinsRolled());
                break;
            case 'wait_score':
                $this->atWaitScore($frame, $message->getPinsRolled());
                break;
        }

        // persist entity
        $this->entityManager->persist($frame);
        $this->entityManager->flush();

        // deliver messages
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
            $this->frameStateMachine->apply($frame, 'roll_bonus');
            yield new PlayerMessage($frame->getPlayer()->getId(), 0);

        } else if ($pins_rolled == Frame::PINS_PER_FRAME) {
            // player scored strike!
            $this->frameStateMachine->apply($frame, 'strike');

            // frame got a bonus for the next one/two rolls
            $frame->setScoreWait(match ($frame->getRound()) {
                Game::FRAMES_PER_GAME => 1,
                default => 2
            });

            // create next player round
            yield new PlayerMessage($frame->getPlayer()->getId(), $frame->getRound() + 1);

        } else {
            // wait for second roll
            $this->frameStateMachine->apply($frame, 'roll_first');
        }

        // force roll propagation to pending frames
        yield new FrameRollPropagation($frame->getId(), $pins_rolled);
    }

    /**
     * User is rolling the second ball for this frame
     *
     * @param Frame $frame
     * @param int $pins_rolled
     * @return iterable
     */
    private function atSecondRoll(Frame $frame, int $pins_rolled): iterable
    {
        // do not allow more pins than frame's
        assert($frame->getRoll1() + $pins_rolled <= Frame::PINS_PER_FRAME);

        // update roll2
        $frame->setRoll2($pins_rolled);

        // all pins are down
        if ($frame->getRoll1() + $frame->getRoll2() == Frame::PINS_PER_FRAME) {
            // player scored spare!
            $this->frameStateMachine->apply($frame, 'spare');

            // frame got a bonus for the next roll
            $frame->setScoreWait(1);
        } else {
            // not all pins went down, frame is done
            $this->frameStateMachine->apply($frame, 'roll_second');
        }

        // decide next frame, 0 means end game
        $next_frame = 0;
        if ($frame->getScore() == Frame::PINS_PER_FRAME || $frame->getRound() < Game::FRAMES_PER_GAME) {
            // create new frame if got spare or game not finished
            $next_frame = $frame->getRound() + 1;
        }

        // propagate score to other frames
        yield new FrameRollPropagation($frame->getId(), $pins_rolled);

        // generate new frame or end of game
        yield new PlayerMessage($frame->getPlayer()->getId(), $next_frame);
    }

    /**
     * Frame is waiting the next rolls in order to get the
     * bonus score after scoring a strike/spare
     *
     * @param Frame $frame
     * @param int $pins_rolled
     * @return void
     */
    private function atWaitScore(Frame $frame, int $pins_rolled): void
    {
        // do not allow more pins than frame's
        assert($pins_rolled <= Frame::PINS_PER_FRAME);
        assert($frame->getScoreWait() > 0);

        // append the pins rolled to the current frame
        $frame->setScore($frame->getScore() + $pins_rolled);

        // decrement wait rolls
        $frame->setScoreWait($frame->getScoreWait() - 1);

        // if pending wait rolls is zero, transition to done
        if ($frame->getScoreWait() == 0) {
            $this->frameStateMachine->apply($frame, 'receive_score_done');
        }
    }
}

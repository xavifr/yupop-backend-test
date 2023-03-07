<?php

namespace App\MessageHandler;

use App\Entity\Frame;
use App\Entity\Game;
use App\Entity\Player;
use App\Message\FrameMessage;
use App\Message\FrameRollPropagationMessage;
use App\Message\PlayerMessage;
use App\Repository\FrameRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Config\Definition\Exception\Exception;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
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
        private ?LoggerInterface       $logger,
    )
    {
    }

    public function __invoke(FrameMessage $message)
    {
        // get entity
        $frame = $this->frameRepository->find($message->getId());

        // check player and game in correct state
        if ($frame->getPlayer()->getGame()->getState() != Game::STATE_PLAYING) {
            throw new Exception("Cannot transition a frame if the game is not running");
        } else if ($frame->getPlayer()->getState() != Player::STATE_PLAYING) {
            throw new Exception("Cannot transition a frame if the player is not playing");
        }

        $this->logger->error(sprintf("Received message for frame %d from player %s in state '%s'", $frame->getRound(), $frame->getPlayer()->getName(), $frame->getState()));

        // initialize new messages to deliver
        $new_messages = [];

        switch ($frame->getState()) {
            case Frame::STATE_NEW:
                $new_messages = $this->atNew($frame, $message->getPinsRolled());
                break;
            case Frame::STATE_SECOND_ROLL:
                $new_messages = $this->atSecondRoll($frame, $message->getPinsRolled());
                break;
            case Frame::STATE_THIRD_ROLL:
                $new_messages = $this->atThirdRoll($frame, $message->getPinsRolled());
                break;
            case Frame::STATE_WAIT_SCORE:
                $this->atWaitScore($frame, $message->getPinsRolled());
                break;
        }

        $this->logger->error(sprintf("  new state is %s", $frame->getState()));

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
     * @return array
     */
    private function atNew(Frame $frame, int $pins_rolled): array
    {
        if ($pins_rolled > Frame::PINS_PER_FRAME) {
            throw new Exception(sprintf("Cannot roll more than %d pins on first roll", Frame::PINS_PER_FRAME));
        }

        $out_messages = [];

        // update roll1
        $frame->setRoll1($pins_rolled);

        // force roll propagation to pending frames
        $out_messages[] = new FrameRollPropagationMessage($frame->getId(), $pins_rolled);

        if ($pins_rolled == Frame::PINS_PER_FRAME) {
            // player scored strike!
            $this->logger->error(sprintf("  strike!"));

            if ($frame->getRound() == Game::FRAMES_PER_GAME) {
                // at last round, allow a bonus roll!
                $this->frameStateMachine->apply($frame, 'strike_bonus');
            } else {
                // frame got a bonus for the next two rolls
                $this->frameStateMachine->apply($frame, 'strike');
                $frame->setScoreWait(2);

                // create next player round
                $out_messages[] = new PlayerMessage($frame->getPlayer()->getId(), $frame->getRound() + 1);
            }
        } else {
            $this->logger->error(sprintf("  rolled %d pins at first roll", $pins_rolled));

            // wait for second roll
            $this->frameStateMachine->apply($frame, 'roll_first');
        }

        return $out_messages;
    }

    /**
     * User is rolling the second ball for this frame
     *
     * @param Frame $frame
     * @param int $pins_rolled
     * @return array
     */
    private function atSecondRoll(Frame $frame, int $pins_rolled): array
    {
        if (($frame->getRoll1()+$pins_rolled) > Frame::PINS_PER_FRAME) {
            throw new Exception(sprintf("Cannot roll more than %d (%d) pins on second roll", Frame::PINS_PER_FRAME-$frame->getRoll1(), $frame->getRoll1()));
        }

        $out_messages = [];
        // update roll2
        $frame->setRoll2($pins_rolled);

        // propagate score to other frames
        $out_messages[] = new FrameRollPropagationMessage($frame->getId(), $pins_rolled);

        // all pins are down
        if ($frame->getRoll1() + $frame->getRoll2() == Frame::PINS_PER_FRAME) {
            // player scored spare!
            $this->logger->error(sprintf("  spare!"));

            if ($frame->getRound() == Game::FRAMES_PER_GAME) {
                $this->frameStateMachine->apply($frame, 'spare_bonus');
            } else {
                $this->frameStateMachine->apply($frame, 'spare');

                // frame got a bonus for the next roll
                $frame->setScoreWait(1);

            }
        } else {
            $this->logger->error(sprintf("  rolled %d pins at second roll", $pins_rolled));

            // not all pins went down, frame is done
            $this->frameStateMachine->apply($frame, 'roll_second');
        }

        if ($frame->getState() != Frame::STATE_THIRD_ROLL) {
            // generate new frame or end of game
            $out_messages[] = new PlayerMessage($frame->getPlayer()->getId(), match ($frame->getRound()) {
                Game::FRAMES_PER_GAME => 0,
                default => $frame->getRound() + 1
            });
        }


        return $out_messages;
    }

    /**
     * User is rolling the third ball for this frame
     *
     * @param Frame $frame
     * @param int $pins_rolled
     * @return array
     */
    private function atThirdRoll(Frame $frame, int $pins_rolled): array
    {
        if ($pins_rolled > Frame::PINS_PER_FRAME) {
            throw new Exception(sprintf("Cannot roll more than %d pins on third roll", Frame::PINS_PER_FRAME));
        }

        $out_messages = [];

        // update roll3
        $frame->setRoll3($pins_rolled);

        // frame is done
        $this->frameStateMachine->apply($frame, 'roll_third');

        // propagate score to other frames
        $out_messages[] = new FrameRollPropagationMessage($frame->getId(), $pins_rolled);

        // generate end of game
        $out_messages[] = new PlayerMessage($frame->getPlayer()->getId(), 0);

        return $out_messages;
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
        if ($pins_rolled > Frame::PINS_PER_FRAME) {
            throw new Exception(sprintf("Cannot receive more than %d pins on wait state", Frame::PINS_PER_FRAME));
        }

        $this->logger->error(sprintf("  received a propagation score of %d", $pins_rolled));

        // append the pins rolled to the current frame
        $frame->setScore($frame->getScore() + $pins_rolled);

        // decrement wait rolls
        $frame->setScoreWait($frame->getScoreWait() - 1);

        // if pending wait rolls is zero, transition to done
        if ($frame->getScoreWait() == 0) {
            $this->logger->error(sprintf("  closing frame"));

            $this->frameStateMachine->apply($frame, 'receive_score_done');
        }
    }
}

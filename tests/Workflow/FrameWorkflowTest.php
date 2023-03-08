<?php

namespace App\Tests\Workflow;

use App\Entity\Frame;
use App\Entity\Game;
use App\Entity\Player;
use App\Message\FrameMessage;
use App\MessageHandler\FrameMessageHandler;
use App\Repository\FrameRepository;
use App\Repository\GameRepository;
use App\Repository\PlayerRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Workflow\WorkflowInterface;

class FrameWorkflowTest extends KernelTestCase
{
    private FrameRepository $frameRepository;
    private GameRepository $gameRepository;

    private EntityManagerInterface $entityManager;
    private WorkflowInterface $frameStateMachine;
    private MessageBusInterface $bus;
    private FrameMessageHandler $frameMessageHandler;

    public function testFirstRollStrike(): void
    {
        $this->initializeFirstRoll($game, $player, $frame);

        ($this->frameMessageHandler)(new FrameMessage($frame->getId(), Frame::PINS_PER_FRAME));

        $this->assertEquals(Frame::STATE_WAIT_SCORE, $frame->getState());
        $this->assertEquals(2, $frame->getScoreWait());
        $this->assertEquals(Frame::PINS_PER_FRAME, $frame->getRoll1());
        $this->assertEquals(Frame::PINS_PER_FRAME, $frame->getScore());
        $this->assertEquals(Frame::PINS_PER_FRAME, $player->getFinalScore());
    }

    private function initializeFirstRoll(?Game &$game, ?Player &$player, ?Frame &$frame)
    {
        $this->initializePlayer($game, $player);

        $frame = new Frame();
        $frame->setRound(1);
        $frame->setPlayer($player);
        $player->addFrame($frame);

        $this->entityManager->persist($frame);
    }

    private function initializePlayer(?Game &$game, ?Player &$player)
    {
        $this->initializeGame($game);

        /** @var Player $players */
        $player = $game->getPlayers()->first();
        $player->setState(Player::STATE_PLAYING);
        $this->entityManager->persist($player);
    }

    private function initializeGame(?Game &$game)
    {
        $game = $this->gameRepository->findOneByReference("random_reference_2");
        $game->setState(Game::STATE_PLAYING);
        $this->entityManager->persist($game);

    }

    public function testFirstRollPins(): void
    {
        $this->initializeFirstRoll($game, $player, $frame);

        ($this->frameMessageHandler)(new FrameMessage($frame->getId(), 2));

        $this->assertEquals(Frame::STATE_SECOND_ROLL, $frame->getState());
        $this->assertEquals(2, $frame->getRoll1());
        $this->assertEquals(2, $frame->getScore());
        $this->assertEquals(0, $player->getFinalScore());
    }

    public function testSecondRollPins(): void
    {
        $this->initializeFirstRoll($game, $player, $frame);

        ($this->frameMessageHandler)(new FrameMessage($frame->getId(), 2));
        ($this->frameMessageHandler)(new FrameMessage($frame->getId(), 3));

        $this->assertEquals(Frame::STATE_DONE, $frame->getState());
        $this->assertEquals(2, $frame->getRoll1());
        $this->assertEquals(3, $frame->getRoll2());
        $this->assertEquals(5, $frame->getScore());
        $this->assertEquals(5, $player->getFinalScore());
    }

    public function testSecondRollSpare(): void
    {
        $this->initializeFirstRoll($game, $player, $frame);

        ($this->frameMessageHandler)(new FrameMessage($frame->getId(), 2));
        ($this->frameMessageHandler)(new FrameMessage($frame->getId(), Frame::PINS_PER_FRAME - 2));

        $this->assertEquals(Frame::STATE_WAIT_SCORE, $frame->getState());
        $this->assertEquals(1, $frame->getScoreWait());
        $this->assertEquals(2, $frame->getRoll1());
        $this->assertEquals(Frame::PINS_PER_FRAME - 2, $frame->getRoll2());
        $this->assertEquals(Frame::PINS_PER_FRAME, $frame->getScore());
        $this->assertEquals(Frame::PINS_PER_FRAME, $player->getFinalScore());
    }

    public function testFirstRollStrikeBonus(): void
    {
        $this->initializeFirstRoll($game, $player, $frame);

        $frame->setRound(Game::FRAMES_PER_GAME);
        $this->entityManager->persist($frame);

        ($this->frameMessageHandler)(new FrameMessage($frame->getId(), Frame::PINS_PER_FRAME));

        $this->assertEquals(Frame::STATE_THIRD_ROLL, $frame->getState());
        $this->assertEquals(Frame::PINS_PER_FRAME, $frame->getRoll1());
        $this->assertEquals(Frame::PINS_PER_FRAME, $frame->getScore());
    }

    public function testSecondRollSpareBonus(): void
    {
        $this->initializeFirstRoll($game, $player, $frame);

        $frame->setRound(Game::FRAMES_PER_GAME);
        $this->entityManager->persist($frame);

        ($this->frameMessageHandler)(new FrameMessage($frame->getId(), 2));
        ($this->frameMessageHandler)(new FrameMessage($frame->getId(), Frame::PINS_PER_FRAME - 2));

        $this->assertEquals(Frame::STATE_THIRD_ROLL, $frame->getState());
        $this->assertEquals(2, $frame->getRoll1());
        $this->assertEquals(Frame::PINS_PER_FRAME - 2, $frame->getRoll2());
        $this->assertEquals(Frame::PINS_PER_FRAME, $frame->getScore());
    }

    public function testThirdRoll(): void
    {
        $this->initializeFirstRoll($game, $player, $frame);

        $frame->setRound(Game::FRAMES_PER_GAME);
        $this->entityManager->persist($frame);

        ($this->frameMessageHandler)(new FrameMessage($frame->getId(), 2));
        ($this->frameMessageHandler)(new FrameMessage($frame->getId(), Frame::PINS_PER_FRAME - 2));
        ($this->frameMessageHandler)(new FrameMessage($frame->getId(), 5));

        $this->assertEquals(Frame::STATE_DONE, $frame->getState());
        $this->assertEquals(2, $frame->getRoll1());
        $this->assertEquals(Frame::PINS_PER_FRAME - 2, $frame->getRoll2());
        $this->assertEquals(5, $frame->getRoll3());
        $this->assertEquals(Frame::PINS_PER_FRAME + 5 + 5, $frame->getScore());
        $this->assertEquals(Frame::PINS_PER_FRAME + 5 + 5, $player->getFinalScore());
    }

    public function testScoreWait(): void
    {
        // two frames, first with strike, second new
        $this->initializeStrikeAndFrame($game, $player, $frame1, $frame2);

        // roll 2 pins at frame2
        ($this->frameMessageHandler)(new FrameMessage($frame2->getId(), 2));

        // check that those 2 pins are propagated to frame1, still waiting
        $this->assertEquals(Frame::STATE_WAIT_SCORE, $frame1->getState());
        $this->assertEquals(1, $frame1->getScoreWait());
        $this->assertEquals(Frame::PINS_PER_FRAME + 2, $frame1->getScore());

        // roll 3 pins at frame2
        ($this->frameMessageHandler)(new FrameMessage($frame2->getId(), 3));

        // check that those 3 pins are propagated to frame1 and frame is done
        $this->assertEquals(Frame::STATE_DONE, $frame1->getState());
        $this->assertEquals(0, $frame1->getScoreWait());
        $this->assertEquals(Frame::PINS_PER_FRAME + 5, $frame1->getScore());
        $this->assertEquals(Frame::PINS_PER_FRAME + 5 + 5, $player->getFinalScore());
    }

    private function initializeStrikeAndFrame(?Game &$game, ?Player &$player, ?Frame &$frame1, ?Frame &$frame2)
    {
        $this->initializePlayer($game, $player);

        $frame1 = new Frame();
        $frame1->setRound(1)->setPlayer($player)->setRoll1(Frame::PINS_PER_FRAME)->setState(Frame::STATE_WAIT_SCORE)->setScoreWait(2);
        $player->addFrame($frame1);
        $this->entityManager->persist($frame1);

        $frame2 = new Frame();
        $frame2->setRound(2)->setPlayer($player);
        $player->addFrame($frame2);
        $this->entityManager->persist($frame2);
    }

    protected function setUp(): void
    {
        /** @var PlayerRepository $playerRepository */
        $this->frameRepository = static::getContainer()->get(FrameRepository::class);
        $this->gameRepository = static::getContainer()->get(GameRepository::class);
        $this->entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $this->frameStateMachine = static::getContainer()->get('state_machine.frame');
        $this->bus = static::getContainer()->get(MessageBusInterface::class);

        $this->frameMessageHandler = new FrameMessageHandler($this->entityManager, $this->frameRepository, $this->frameStateMachine, $this->bus);
    }
}

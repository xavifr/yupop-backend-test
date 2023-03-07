<?php

namespace App\Tests\UseCase;

use App\Entity\Frame;
use App\Entity\Game;
use App\Entity\Player;
use App\Message\FrameMessage;
use App\Message\GameMessage;
use App\MessageHandler\FrameMessageHandler;
use App\MessageHandler\GameMessageHandler;
use App\Repository\FrameRepository;
use App\Repository\GameRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Workflow\WorkflowInterface;

class GameTest extends KernelTestCase
{
    private GameRepository $gameRepository;
    private FrameRepository $frameRepository;
    private EntityManagerInterface $entityManager;
    private WorkflowInterface $gameStateMachine;
    private WorkflowInterface $frameStateMachine;
    private MessageBusInterface $bus;
    private LoggerInterface $logger;
    private GameMessageHandler $gameMessageHandler;
    private FrameMessageHandler $frameMessageHandler;

    protected function setUp(): void
    {
        /** @var GameRepository $gameRepository */
        $this->gameRepository = static::getContainer()->get(GameRepository::class);
        $this->frameRepository = static::getContainer()->get(FrameRepository::class);
        $this->entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $this->gameStateMachine = static::getContainer()->get('state_machine.game');
        $this->frameStateMachine = static::getContainer()->get('state_machine.frame');
        $this->bus = static::getContainer()->get(MessageBusInterface::class);
        $this->logger = static::getContainer()->get(LoggerInterface::class);

        $this->gameMessageHandler = new GameMessageHandler($this->entityManager, $this->gameRepository, $this->gameStateMachine, $this->bus, $this->logger);
        $this->frameMessageHandler = new FrameMessageHandler($this->entityManager, $this->frameRepository, $this->frameStateMachine, $this->bus, $this->logger);
    }

    public function testFastGame(): void {
        $game = $this->gameRepository->findOneByReference("random_reference_2");
        $this->assertEquals(Game::STATE_NEW, $game->getState());

        ($this->gameMessageHandler)(new GameMessage($game->getId()));
        $this->assertEquals(Game::STATE_PLAYING, $game->getState());

        $player = $game->getPlayers()->first();
        $this->assertEquals(Player::STATE_PLAYING, $player->getState());

        for($i =1; $i <= Game::FRAMES_PER_GAME; $i++) {
            $this->entityManager->refresh($player);

            /** @var Frame $frame */
            $frame = $player->getFrames()->filter(function(Frame $f) {
                return in_array($f->getState(), [Frame::STATE_NEW, Frame::STATE_SECOND_ROLL]);
            })->first();

            ($this->frameMessageHandler)(new FrameMessage($frame->getId(), 1));
            ($this->frameMessageHandler)(new FrameMessage($frame->getId(), 1));

            $this->assertEquals(Frame::STATE_DONE, $frame->getState());
        }

        $this->entityManager->refresh($player);

        // game must have finished
        $this->assertEquals(Game::STATE_FINISHED, $game->getState());

        // players final score must be 20
        $this->assertEquals(20, $player->getFinalScore());

        // game's winner must be the player
        $this->assertEquals($player->getId(), $game->getWinnerPlayer()->getId());
    }
}

<?php

namespace App\Tests\Workflow;

use App\Entity\Frame;
use App\Entity\Game;
use App\Entity\Player;
use App\Message\GameMessage;
use App\MessageHandler\GameMessageHandler;
use App\Repository\GameRepository;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\VarDumper\Caster\FrameStub;
use Symfony\Component\Workflow\WorkflowInterface;

class GameWorkflowTest extends KernelTestCase
{
    private GameRepository $gameRepository;
    private EntityManagerInterface $entityManager;
    private WorkflowInterface $gameStateMachine;
    private MessageBusInterface $bus;
    private LoggerInterface $logger;
    private GameMessageHandler $gameMessageHandler;

    protected function setUp(): void
    {
        /** @var GameRepository $gameRepository */
        $this->gameRepository = static::getContainer()->get(GameRepository::class);
        $this->entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $this->gameStateMachine = static::getContainer()->get('state_machine.game');
        $this->bus = static::getContainer()->get(MessageBusInterface::class);
        $this->logger = static::getContainer()->get(LoggerInterface::class);

        $this->gameMessageHandler = new GameMessageHandler($this->entityManager, $this->gameRepository, $this->gameStateMachine, $this->bus, $this->logger);
    }

    public function testNewToPlaying(): void
    {
        $game = $this->gameRepository->findOneByReference("random_reference_2");
        $this->assertEquals(Game::STATE_NEW, $game->getState());

        ($this->gameMessageHandler)(new GameMessage($game->getId()));

        $this->assertEquals(Game::STATE_PLAYING, $game->getState());

    }

    public function testPlayingToFinished(): void
    {
        $game = $this->gameRepository->findOneByReference("random_reference_2");
        $this->assertEquals(Game::STATE_NEW, $game->getState());

        ($this->gameMessageHandler)(new GameMessage($game->getId()));
        $this->assertEquals(Game::STATE_PLAYING, $game->getState());

        $player = $game->getPlayers()->first();
        $this->assertEquals(Player::STATE_PLAYING, $player->getState());
        $player->setState(Player::STATE_FINISHED);

        ($this->gameMessageHandler)(new GameMessage($game->getId()));
        $this->assertEquals(Game::STATE_FINISHED, $game->getState());

    }

}

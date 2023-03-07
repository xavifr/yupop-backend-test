<?php

namespace App\Tests\Workflow;

use App\Entity\Frame;
use App\Entity\Game;
use App\Entity\Player;
use App\Message\PlayerMessage;
use App\MessageHandler\PlayerMessageHandler;
use App\Repository\FrameRepository;
use App\Repository\GameRepository;
use App\Repository\PlayerRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Workflow\WorkflowInterface;

class PlayerWorkflowTest extends KernelTestCase
{
    private PlayerRepository $playerRepository;
    private FrameRepository $frameRepository;
    private GameRepository $gameRepository;

    private EntityManagerInterface $entityManager;
    private WorkflowInterface $playerStateMachine;
    private MessageBusInterface $bus;
    private PlayerMessageHandler $playerMessageHandler;

    public function testPlayingToWaitingToPlaying(): void
    {
        $game = $this->gameRepository->findOneByReference("random_reference_3");

        /** @var Player[] $players */
        $players = $game->getPlayers();
        $this->assertCount(2, $players);
        $this->assertCount(1, $players[0]->getFrames());
        $this->assertCount(0, $players[1]->getFrames());
        $this->assertEquals(Player::STATE_PLAYING, $players[0]->getState());
        $this->assertCount(1, $players[0]->getFrames());

        $player_frame = $players[0]->getFrames()->first();
        $player_frame->setRoll1(1)->setRoll2(1)->setState(Frame::STATE_DONE);
        $this->entityManager->persist($player_frame);

        ($this->playerMessageHandler)(new PlayerMessage($players[0]->getId()));

        $this->assertEquals(Player::STATE_WAITING, $players[0]->getState());
        $this->assertCount(2, $players[0]->getFrames());

        $this->assertEquals(Player::STATE_PLAYING, $players[1]->getState());
        $this->assertCount(1, $players[1]->getFrames());
    }

    public function testPlayingToFinished(): void
    {
        $game = $this->gameRepository->findOneByReference("random_reference_3");

        /** @var Player[] $players */
        $players = $game->getPlayers();

        foreach ($players as $player) {
            while ($player->getFrames()->count() < Game::FRAMES_PER_GAME) {
                $frame = new Frame();
                $frame->setRound($player->getFrames()->count() + 1)->setRoll1(1)->setRoll2(1)->setState(Frame::STATE_DONE);
                $player->addFrame($frame);
                $this->entityManager->persist($frame);
            }
            $this->entityManager->persist($player);
        }

        ($this->playerMessageHandler)(new PlayerMessage($players[0]->getId()));
        ($this->playerMessageHandler)(new PlayerMessage($players[1]->getId()));

        $this->assertEquals(Player::STATE_FINISHED, $players[0]->getState());
        $this->assertEquals(Player::STATE_FINISHED, $players[1]->getState());
    }

    protected function setUp(): void
    {
        /** @var PlayerRepository $playerRepository */
        $this->playerRepository = static::getContainer()->get(PlayerRepository::class);
        $this->frameRepository = static::getContainer()->get(FrameRepository::class);
        $this->gameRepository = static::getContainer()->get(GameRepository::class);
        $this->entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $this->playerStateMachine = static::getContainer()->get('state_machine.player');
        $this->bus = static::getContainer()->get(MessageBusInterface::class);

        $this->playerMessageHandler = new PlayerMessageHandler($this->entityManager, $this->playerRepository, $this->frameRepository, $this->playerStateMachine, $this->bus);
    }

}

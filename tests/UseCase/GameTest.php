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

    public function testFastOnePlayerGame(): void
    {
        // get game from db (game with 1 player not started)
        $game = $this->gameRepository->findOneByReference("random_reference_2");
        $this->assertEquals(Game::STATE_NEW, $game->getState());

        // generate a game message (NEW => PLAYING)
        ($this->gameMessageHandler)(new GameMessage($game->getId()));

        // game is playing
        $this->assertEquals(Game::STATE_PLAYING, $game->getState());

        // player is playing
        $player = $game->getPlayers()->first();
        $this->assertEquals(Player::STATE_PLAYING, $player->getState());

        // throw 1 pin per throw in all frames
        for ($i = 1; $i <= Game::FRAMES_PER_GAME; $i++) {
            /** @var Frame $frame */
            $frame = $player->getFrames()->filter(function (Frame $f) {
                return in_array($f->getState(), [Frame::STATE_NEW, Frame::STATE_SECOND_ROLL]);
            })->first();

            ($this->frameMessageHandler)(new FrameMessage($frame->getId(), 1));
            ($this->frameMessageHandler)(new FrameMessage($frame->getId(), 1));

            $this->assertEquals(Frame::STATE_DONE, $frame->getState());
        }

        // game must have finished
        $this->assertEquals(Game::STATE_FINISHED, $game->getState());

        // players final score must be 20
        $this->assertEquals(20, $player->getFinalScore());

        // game's winner must be the player
        $this->assertEquals($player->getId(), $game->getWinnerPlayer()->getId());
    }

    public function testFastTwoPlayersGame(): void
    {
        // get new game with two players
        $game = $this->gameRepository->findOneByReference("random_reference_4");

        // generate a game message (NEW => PLAYING)
        ($this->gameMessageHandler)(new GameMessage($game->getId()));

        // game is playing
        $this->assertEquals(Game::STATE_PLAYING, $game->getState());

        $players = $game->getPlayers();

        // throw 1 pin per roll for player1 and 2 pins for player2
        for ($i = 1; $i <= Game::FRAMES_PER_GAME; $i++) {
            foreach ($players as $j => $player) {
                /** @var Frame $frame */
                $frame = $player->getFrames()->filter(function (Frame $f) {
                    return in_array($f->getState(), [Frame::STATE_NEW, Frame::STATE_SECOND_ROLL]);
                })->first();

                // player[0] rolls always 1, player[1] rolls always 2
                ($this->frameMessageHandler)(new FrameMessage($frame->getId(), $j + 1));
                ($this->frameMessageHandler)(new FrameMessage($frame->getId(), $j + 1));

                $this->assertEquals(Frame::STATE_DONE, $frame->getState());
            }
        }

        // game must have finished
        $this->assertEquals(Game::STATE_FINISHED, $game->getState());

        // players final score must be 20 and 40
        $this->assertEquals(20, $players[0]->getFinalScore());
        $this->assertEquals(40, $players[1]->getFinalScore());

        // game's winner must be the player
        $this->assertEquals($players[1]->getId(), $game->getWinnerPlayer()->getId());
    }

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
}

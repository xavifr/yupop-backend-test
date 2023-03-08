<?php

namespace App\Tests\Controller;

use App\Entity\Frame;
use App\Entity\Game;
use App\Entity\Player;
use App\Repository\GameRepository;
use App\Repository\PlayerRepository;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class GameControllerTest extends WebTestCase
{
    public function testViewCreate(): void
    {
        $client = static::createClient();
        $crawler = $client->request('GET', '/');

        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('h2', 'Yupop Bowling');
    }

    public function testPostCreate(): void
    {
        $client = static::createClient();
        $crawler = $client->request('GET', '/');

        $this->assertResponseIsSuccessful();

        $game_name = 'Some new game';
        $client->submitForm('Create', [
            'new_game_form[name]' => $game_name,
        ]);

        $this->assertResponseRedirects();
        $client->followRedirect();
        $this->assertStringContainsStringIgnoringCase($game_name, $client->getResponse()->getContent());

        $uri_arr = explode('/', $client->getRequest()->getUri());

        /** @var Game $game */
        $game = static::getContainer()->get(GameRepository::class)->findOneByReference(end($uri_arr));
        $this->assertNotEmpty($game);
        $this->assertEquals($game_name, $game->getName());

    }

    public function testPlayerCreate(): void
    {
        $client = static::createClient();
        $crawler = $client->request('GET', '/setup/random_reference_1');

        $this->assertResponseIsSuccessful();
        $this->assertStringContainsStringIgnoringCase("Predefined game name", $client->getResponse()->getContent());

        /** @var Game $game */
        $game = static::getContainer()->get(GameRepository::class)->findOneByReference("random_reference_1");
        $this->assertEquals(0, $game->getPlayers()->count());

        $player1_name = 'Some random name';
        $player2_name = 'Another random name';
        $client->submitForm('Add Player', [
            'new_player_form[name]' => $player1_name,
            'new_player_form[addPlayer]' => true,
        ]);
        $this->assertResponseIsSuccessful();

        $this->assertStringContainsStringIgnoringCase($player1_name, $client->getResponse()->getContent());
        $this->assertStringNotContainsStringIgnoringCase($player2_name, $client->getResponse()->getContent());

        $client->submitForm('Add Player', [
            'new_player_form[name]' => $player2_name,
            'new_player_form[addPlayer]' => true,
        ]);
        $this->assertResponseIsSuccessful();

        $this->assertStringContainsStringIgnoringCase($player2_name, $client->getResponse()->getContent());

        /** @var Game $game */
        $game = static::getContainer()->get(GameRepository::class)->findOneByReference("random_reference_1");
        $this->assertEquals(2, $game->getPlayers()->count());

        $player1 = static::getContainer()->get(PlayerRepository::class)->findOneBy(['game' => $game, 'name' => $player1_name]);
        $this->assertNotEmpty($player1);
        $this->assertEquals($player1_name, $player1->getName());
        $this->assertEquals(1, $player1->getPosition());

        $player2 = static::getContainer()->get(PlayerRepository::class)->findOneBy(['game' => $game, 'name' => $player2_name]);
        $this->assertNotEmpty($player2);
        $this->assertEquals($player2_name, $player2->getName());
        $this->assertEquals(2, $player2->getPosition());
    }

    public function testGameStart(): void
    {
        $client = static::createClient();

        /** @var GameRepository $gameRepository */
        $gameRepository = static::getContainer()->get(GameRepository::class);

        // Check initial objects state
        $game = $gameRepository->findOneByReference("random_reference_2");
        $this->assertEquals(Game::STATE_NEW, $game->getState());

        $player1 = $game->getPlayers()->first();
        $this->assertEquals(Player::STATE_WAITING, $player1->getState());

        $player1_frames = $player1->getFrames();
        $this->assertCount(0, $player1_frames);

        // Request setup for game
        $crawler = $client->request('GET', '/setup/random_reference_2');
        $this->assertResponseIsSuccessful();
        $this->assertStringContainsStringIgnoringCase("Second game with one player", $client->getResponse()->getContent());

        // Start game
        $client->submitForm('Start Game', [
            'new_player_form[startGame]' => true,
        ]);

        // Check show page is correct
        $this->assertResponseRedirects();
        $client->followRedirect();
        $this->assertStringContainsStringIgnoringCase($game->getName(), $client->getResponse()->getContent());

        // Check state for objects after game start
        $game = $gameRepository->findOneByReference("random_reference_2");
        $this->assertEquals(Game::STATE_PLAYING, $game->getState());

        $player1 = $game->getPlayers()->first();
        $this->assertEquals(Player::STATE_PLAYING, $player1->getState());

        $player1_frames = $player1->getFrames();
        $this->assertCount(1, $player1_frames);

        $this->assertEquals(Frame::STATE_NEW, $player1_frames->first()->getState());
    }

    public function testGameStartNoPlayers(): void
    {
        $client = static::createClient();

        // Request setup for game
        $crawler = $client->request('GET', '/setup/random_reference_1');
        $this->assertResponseIsSuccessful();

        // Start game
        $client->submitForm('Start Game', [
            'new_player_form[startGame]' => true,
        ]);

        $this->assertResponseIsUnprocessable();
    }

    public function testGameStartAlreadyStarted(): void
    {
        $client = static::createClient();

        // Set game as playing using entitymanager
        $game = static::getContainer()->get(GameRepository::class)->findOneByReference("random_reference_2");
        $game->setState(Game::STATE_PLAYING);
        static::getContainer()->get(GameRepository::class)->save($game);

        // Request setup for game
        $crawler = $client->request('GET', '/setup/random_reference_2');
        $this->assertResponseRedirects();

    }

    public function testGameShowNew(): void
    {
        $client = static::createClient();

        // Request setup for game
        $crawler = $client->request('GET', '/show/random_reference_1');
        $this->assertResponseRedirects();

    }


    public function testGameRoll(): void
    {
        $client = static::createClient();

        /** @var GameRepository $gameRepository */
        $gameRepository = static::getContainer()->get(GameRepository::class);

        // Check initial objects state
        $game = $gameRepository->findOneByReference("random_reference_3");
        $this->assertEquals(Game::STATE_PLAYING, $game->getState());

        $player1 = $game->getPlayers()->first();
        $this->assertEquals(Player::STATE_PLAYING, $player1->getState());

        $player1_frames = $player1->getFrames();
        $this->assertCount(1, $player1_frames);
        $this->assertEquals(Frame::STATE_NEW, $player1_frames[0]->getState());

        // Request setup for game
        $crawler = $client->request('GET', '/show/random_reference_3');
        $this->assertResponseIsSuccessful();
        $this->assertStringContainsStringIgnoringCase("Third game with players and frame", $client->getResponse()->getContent());

        // Check not contains any strike
        $this->assertSelectorNotExists('div:contains("X")');

        // Start game
        $client->submitForm('roll10', [
            'frame_roll_form[roll]' => Frame::PINS_PER_FRAME,
        ]);

        $this->assertResponseIsSuccessful();

        // Check contains a strike
        $this->assertSelectorExists('div:contains("X")');

    }
}

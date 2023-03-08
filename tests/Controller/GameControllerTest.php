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

        // request homepage (create)
        $crawler = $client->request('GET', '/');

        // response is valid
        $this->assertResponseIsSuccessful();

        // content has default title
        $this->assertSelectorTextContains('h2', 'Yupop Bowling');
    }

    public function testPostCreate(): void
    {
        $client = static::createClient();

        // request homepage (create)
        $crawler = $client->request('GET', '/');

        $this->assertResponseIsSuccessful();

        // submit form with name
        $game_name = 'Some new game';
        $client->submitForm('Create', [
            'new_game_form[name]' => $game_name,
        ]);

        // check response is ok
        $this->assertResponseRedirects();
        $client->followRedirect();

        // check game matches
        $this->assertSelectorExists(sprintf('h3:contains("%s")', strtoupper($game_name)));

        // get uri as array, last term is reference
        $uri_arr = explode('/', $client->getRequest()->getUri());

        /** @var Game $game */
        $game = static::getContainer()->get(GameRepository::class)->findOneByReference(end($uri_arr));

        // check game exists in db with same name
        $this->assertNotEmpty($game);
        $this->assertEquals($game_name, $game->getName());

    }

    public function testPlayerCreate(): void
    {
        $client = static::createClient();

        // setup game
        $crawler = $client->request('GET', '/setup/random_reference_1');

        // response is ok
        $this->assertResponseIsSuccessful();
        $this->assertSelectorExists(sprintf('h3:contains("%s")', strtoupper("Predefined game name")));

        // check there are no players in game
        /** @var Game $game */
        $game = static::getContainer()->get(GameRepository::class)->findOneByReference("random_reference_1");
        $this->assertEquals(0, $game->getPlayers()->count());

        // initialize player names
        $player1_name = 'Some random name';
        $player2_name = 'Another random name';

        // add first player
        $client->submitForm('Add Player', [
            'new_player_form[name]' => $player1_name,
            'new_player_form[addPlayer]' => true,
        ]);
        $this->assertResponseIsSuccessful();

        // only first player exists
        $this->assertSelectorExists(sprintf('li:contains("%s")', $player1_name));
        $this->assertSelectorNotExists(sprintf('li:contains("%s")', $player2_name));

        // add second player
        $client->submitForm('Add Player', [
            'new_player_form[name]' => $player2_name,
            'new_player_form[addPlayer]' => true,
        ]);
        $this->assertResponseIsSuccessful();

        // second player exists
        $this->assertSelectorExists(sprintf('li:contains("%s")', $player2_name));

        // get game from db
        /** @var Game $game */
        $game = static::getContainer()->get(GameRepository::class)->findOneByReference("random_reference_1");
        $this->assertEquals(2, $game->getPlayers()->count());

        // check player1 on db
        $player1 = static::getContainer()->get(PlayerRepository::class)->findOneBy(['game' => $game, 'name' => $player1_name]);
        $this->assertNotEmpty($player1);
        $this->assertEquals($player1_name, $player1->getName());
        $this->assertEquals(1, $player1->getPosition());

        // check player2 on db
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

        // check player is waiting
        $player1 = $game->getPlayers()->first();
        $this->assertEquals(Player::STATE_WAITING, $player1->getState());

        // player with no frames
        $player1_frames = $player1->getFrames();
        $this->assertCount(0, $player1_frames);

        // Request setup for game
        $crawler = $client->request('GET', '/setup/random_reference_2');
        $this->assertResponseIsSuccessful();
        $this->assertSelectorExists(sprintf('h3:contains("%s")', strtoupper("Second game with one player")));

        // Start game
        $client->submitForm('Start Game', [
            'new_player_form[startGame]' => true,
        ]);

        // Check show page is correct
        $this->assertResponseRedirects();
        $client->followRedirect();
        $this->assertSelectorExists(sprintf('h1:contains("%s")', strtoupper($game->getName())));

        // Check state for objects after game start
        $game = $gameRepository->findOneByReference("random_reference_2");
        $this->assertEquals(Game::STATE_PLAYING, $game->getState());

        // first player is playing
        $player1 = $game->getPlayers()->first();
        $this->assertEquals(Player::STATE_PLAYING, $player1->getState());

        // player1 has frames
        $player1_frames = $player1->getFrames();
        $this->assertCount(1, $player1_frames);

        // first frame is in new state
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

        // cannot start game without players
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

        // cannot access setup in an already started game (goes to show)
        $this->assertResponseRedirects();

    }

    public function testGameShowNew(): void
    {
        $client = static::createClient();

        // Request setup for game
        $crawler = $client->request('GET', '/show/random_reference_1');

        // cannot show a game that has not been started (goes to setup)
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

        // player 1 is playing
        $player1 = $game->getPlayers()->first();
        $this->assertEquals(Player::STATE_PLAYING, $player1->getState());

        // frame 1 is in new state
        $player1_frames = $player1->getFrames();
        $this->assertCount(1, $player1_frames);
        $this->assertEquals(Frame::STATE_NEW, $player1_frames[0]->getState());

        // Request setup for game
        $crawler = $client->request('GET', '/show/random_reference_3');
        $this->assertResponseIsSuccessful();
        $this->assertSelectorExists(sprintf('h1:contains("%s")', strtoupper("Third game with players and frame")));

        // Check not contains any strike
        $this->assertSelectorNotExists('div:contains("X")');

        // Roll and strike
        $client->submitForm('roll10', [
            'frame_roll_form[roll]' => Frame::PINS_PER_FRAME,
        ]);

        $this->assertResponseIsSuccessful();

        // Check contains a strike
        $this->assertSelectorExists('div:contains("X")');

    }
}

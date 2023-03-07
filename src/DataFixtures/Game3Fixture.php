<?php

namespace App\DataFixtures;

use App\Entity\Frame;
use App\Entity\Game;
use App\Entity\Player;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;

class Game3Fixture extends Fixture
{
    public function load(ObjectManager $manager): void
    {
        // Third Game
        $game = new Game();
        $game->setName("Third game with players and frame")->setState(Game::STATE_PLAYING);
        $manager->persist($game);
        $game->setReference("random_reference_3");

        // .. Player 1
        $player = new Player();
        $player->setName("player1")->setGame($game)->setState(Player::STATE_PLAYING);
        $manager->persist($player);
        $game->addPlayer($player);

        // .. .. Frame 1
        $frame = new Frame();
        $frame->setRound(1);
        $frame->setPlayer($player);
        $manager->persist($frame);
        $player->addFrame($frame);

        // .. Player 2
        $player = new Player();
        $player->setName("player2")->setGame($game);
        $manager->persist($player);
        $game->addPlayer($player);

        // Save all
        $manager->flush();
    }
}

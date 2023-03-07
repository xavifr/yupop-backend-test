<?php

namespace App\DataFixtures;

use App\Entity\Game;
use App\Entity\Player;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;

class Game4Fixture extends Fixture
{
    public function load(ObjectManager $manager): void
    {
        // Third Game
        $game = new Game();
        $game->setName("Fourth game with players and no frame");
        $manager->persist($game);
        $game->setReference("random_reference_4");

        // .. Player 1
        $player = new Player();
        $player->setName("player1")->setGame($game);
        $manager->persist($player);
        $game->addPlayer($player);

        // .. Player 2
        $player = new Player();
        $player->setName("player2")->setGame($game);
        $manager->persist($player);
        $game->addPlayer($player);

        // Save all
        $manager->flush();
    }
}

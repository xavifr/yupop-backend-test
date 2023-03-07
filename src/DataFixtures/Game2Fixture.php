<?php

namespace App\DataFixtures;

use App\Entity\Frame;
use App\Entity\Game;
use App\Entity\Player;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;

class Game2Fixture extends Fixture
{
    public function load(ObjectManager $manager): void
    {

        // Second game
        $game = new Game();
        $game->setName("Second game with one player");
        $manager->persist($game);
        $game->setReference("random_reference_2");

        // .. Player 1
        $player = new Player();
        $player->setName("player1")->setGame($game);
        $manager->persist($player);
        $game->addPlayer($player);

        // Save all
        $manager->flush();
    }
}

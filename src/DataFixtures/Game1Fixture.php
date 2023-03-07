<?php

namespace App\DataFixtures;

use App\Entity\Game;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;

class Game1Fixture extends Fixture
{
    public function load(ObjectManager $manager): void
    {
        // First game
        $game = new Game();
        $game->setName("Predefined game name");
        $manager->persist($game);
        $game->setReference("random_reference_1");

        // Save all
        $manager->flush();
    }
}

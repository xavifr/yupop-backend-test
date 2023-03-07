<?php

namespace App\Command;

use App\Repository\FrameRepository;
use App\Repository\GameRepository;
use App\Repository\PlayerRepository;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Messenger\MessageBusInterface;

#[AsCommand(
    name: 'TestCommand',
    description: 'Add a short description for your command',
)]
class TestCommand extends Command
{
    public function __construct(
        private MessageBusInterface $bus,
        private GameRepository      $gameRepository,
        private FrameRepository     $frameRepository,
        private PlayerRepository    $playerRepository,
        string                      $name = null)
    {
        parent::__construct($name);
    }

    protected function configure(): void
    {
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $player = $this->playerRepository->find(20);

        printf("PLAYER IS %s\n", $player->getName());
        $frame = $this->frameRepository->findLastFrameForPlayer($player);

        printf("FRAMES CT %s\n", $frame->getId());

        return Command::SUCCESS;
    }
}

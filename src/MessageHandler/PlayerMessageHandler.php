<?php

namespace App\MessageHandler;

use App\Entity\Frame;
use App\Entity\Game;
use App\Entity\Player;
use App\Message\GameMessage;
use App\Message\PlayerMessage;
use App\Repository\PlayerRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\Handler\MessageHandlerInterface;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Workflow\WorkflowInterface;

#[AsMessageHandler]
class PlayerMessageHandler
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private PlayerRepository       $playerRepository,
        private WorkflowInterface      $playerStateMachine,
        private MessageBusInterface    $bus,
    )
    {
    }

    public function __invoke(PlayerMessage $message)
    {
        // get entity
        $player = $this->playerRepository->find($message->getId());

        // initialize new messages to deliver
        $new_messages = [];

        switch ($player->getState()) {
            case 'waiting':
                $this->atWaiting($player);
                break;
            case 'playing':
                $new_messages = $this->atPlaying($player, $message->getNextRound());
                break;
        }

        // persist entity
        $this->entityManager->persist($player);
        $this->entityManager->flush();

        // deliver messages
        array_walk($new_messages, fn($x) => $this->bus->dispatch($x));
    }

    /**
     * The player is waiting for his turn, transition to playing
     *
     * @param Player $player
     * @return void
     */
    private function atWaiting(Player $player): void
    {
        // start_frame to transition to playing
        $this->playerStateMachine->apply($player, 'start_frame');
    }

    /**
     * Player probably finished his frame,
     *  prepare next round or finish his game
     *
     * @param Player $player
     * @param int $next_round
     * @return iterable
     */
    private function atPlaying(Player $player, int $next_round): iterable
    {
        // set player final score
        $final_score = 0;
        $done_frames = $player->getFrames()->filter(function (Frame $frame) {
            return $frame->getState() == 'done';
        });
        foreach ($done_frames as $frame) {
            $final_score += $frame->getScore();
        }

        $player->setFinalScore($final_score);


        if ($next_round == 0) {
            // end game for this player
            $this->playerStateMachine->apply($player, 'end_game');

            yield new GameMessage($player->getGame()->getId());
        } else {
            // create a new frame for next round
            $new_frame = new Frame();
            $new_frame->setPlayer($player);
            $new_frame->setRound($next_round);
            $player->addFrame($new_frame);
            $this->entityManager->persist($new_frame);

            // if not creating at bonus frame
            if ($next_round <= Game::FRAMES_PER_GAME) {
                // transition player to waiting state
                $this->playerStateMachine->apply($player, 'end_frame');

                // force a new player election
                yield new GameMessage($player->getGame()->getId());
            }
        }
    }
}

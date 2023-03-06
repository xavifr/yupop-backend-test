<?php

namespace App\Controller;

use App\Entity\Frame;
use App\Entity\Game;
use App\Entity\Player;
use App\Form\FrameRollFormType;
use App\Form\NewPlayerFormType;
use App\Form\NewGameFormType;
use App\Message\FrameMessage;
use App\Message\GameMessage;
use App\Repository\GameRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Annotation\Route;

class GameController extends AbstractController
{

    public function __construct(
        private EntityManagerInterface $entityManager,
        private MessageBusInterface    $bus,
    )
    {
    }

    #[Route('/', name: 'homepage')]
    public function create(
        Request $request,
    ): Response
    {
        $game = new Game();

        $form = $this->createForm(NewGameFormType::class, $game);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $this->entityManager->persist($game);
            $this->entityManager->flush();

            return $this->redirectToRoute('configure', ['reference' => $game->getReference()]);
        }

        return $this->render('game/create.html.twig', [
            'game' => $game,
            'game_form' => $form,
        ]);
    }


    #[Route('/setup/{reference}', name: 'configure')]
    public function configure(
        Request $request,
        Game    $game,
    ): Response
    {
        $player = new Player();
        $form = $this->createForm(NewPlayerFormType::class, $player);
        $form->handleRequest($request);
        if ($form->isSubmitted()) {
            if ($form->get('addPlayer')->isClicked() && $form->isValid()) {
                $player->setGame($game);
                $this->entityManager->persist($game);

                $game->addPlayer($player);
                $this->entityManager->persist($player);

                $this->entityManager->flush();
            } else if ($form->get('startGame')->isClicked()) {
                $this->bus->dispatch(new GameMessage($game->getId()));
                return $this->redirectToRoute('game_show', ['reference' => $game->getReference()]);
            }
        }

        return $this->render('game/configure.html.twig', [
            'game' => $game,
            'game_form' => $form,
        ]);
    }

    #[Route('/show/{reference}', name: 'game_show')]
    public function show(
        Request $request,
        Game $game,
    ): Response
    {
        // find player in playing state
        $players_playing = $game->getPlayers()->filter(function(Player $player) {
            return $player->getState() == 'playing';
        })->first();

        assert($players_playing != null);

        // find frame alive
        $frame_alive = $players_playing->getFrames()->filter(function(Frame $frame) {
           return $frame->getState() == 'new' || $frame->getState() == 'second_roll';
        })->first();

        assert($frame_alive !== null);

        $form = $this->createForm(FrameRollFormType::class, $frame_alive);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $rolled_pins = $form['roll']->getData();
            assert($frame_alive->getScore() + $rolled_pins < Frame::PINS_PER_FRAME);

            $this->bus->dispatch(new FrameMessage($frame_alive->getId(), $rolled_pins));
        }

        return $this->render('game/show.html.twig', [
            'game' => $game,
            'frame' => $frame_alive,
            'frame_form' => $form,
        ]);
    }
}

<?php

namespace App\Controller;

use App\Entity\Frame;
use App\Entity\Game;
use App\Entity\Player;
use App\Form\FrameRollFormType;
use App\Form\NewGameFormType;
use App\Form\NewPlayerFormType;
use App\Message\FrameMessage;
use App\Message\GameMessage;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormError;
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

            return $this->redirectToRoute('configure_game', ['reference' => $game->getReference()]);
        }

        return $this->render('game/create.html.twig', [
            'game' => $game,
            'game_form' => $form,
        ]);
    }


    #[Route('/setup/{reference}', name: 'configure_game')]
    public function configure(
        Request $request,
        Game    $game,
    ): Response
    {
        if ($game->getState() != Game::STATE_NEW) {
            return $this->redirectToRoute('game_show', ['reference' => $game->getReference()]);
        }

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

                $player = new Player();
                $form = $this->createForm(NewPlayerFormType::class, $player);

            } else if ($form->get('startGame')->isClicked()) {
                if ($game->getPlayers()->count() > 0) {
                    $this->bus->dispatch(new GameMessage($game->getId()));
                    return $this->redirectToRoute('game_show', ['reference' => $game->getReference()]);
                } else {
                    $form->addError(new FormError("Game must have at least one player to be started"));
                }
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
        Game    $game,
    ): Response
    {
        if ($game->getState() == Game::STATE_NEW) {
            return $this->redirectToRoute('configure_game', ['reference' => $game->getReference()]);
        }

        // find player in playing state
        $players_playing = $game->getPlayers()->filter(function (Player $player) {
            return $player->getState() == 'playing';
        })->first();

        if (!empty($players_playing)) {
            // find frame alive
            $frame_alive = $players_playing->getFrames()->filter(function (Frame $frame) {
                return $frame->getState() == 'new' || $frame->getState() == 'second_roll' || $frame->getState() == 'third_roll';
            })->first();

            $form = $this->createForm(FrameRollFormType::class, $frame_alive);
            $form->handleRequest($request);
            if ($form->isSubmitted() && $form->isValid()) {
                $rolled_pins = $form['roll']->getData();
                assert($frame_alive->getScore() + $rolled_pins < Frame::PINS_PER_FRAME);

                $this->bus->dispatch(new FrameMessage($frame_alive->getId(), $rolled_pins));
            }
        } else {
            $form = null;
            $frame_alive = null;
        }

        return $this->render('game/show.html.twig', [
            'game' => $game,
            'frame' => $frame_alive,
            'frame_form' => $form,
        ]);
    }
}

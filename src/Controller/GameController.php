<?php

namespace App\Controller;

use App\Entity\Game;
use App\Entity\Player;
use App\Form\NewPlayerFormType;
use App\Form\NewGameFormType;
use App\Message\CommentMessage;
use App\Message\GameMessage;
use App\Repository\CommentRepository;
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
        Request                           $request,
    ): Response
    {
        $game = new Game();

        $form = $this->createForm(NewGameFormType::class, $game);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $game->setReference("33111");

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
        Request                           $request,
        Game    $game,
    ): Response
    {
        $player = new Player();
        $form = $this->createForm(NewPlayerFormType::class, $player);
        $form->handleRequest($request);
        if ($form->isSubmitted()) {
            if ($form->get('addPlayer')->isClicked() && $form->isValid()) {
                $player->setGame($game);
                $game->addPlayer($player);
                $this->entityManager->persist($player);
                $this->entityManager->persist($game);
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
        Game    $game,
    ): Response
    {
        return $this->render('game/show.html.twig', [
        ]);
    }
}

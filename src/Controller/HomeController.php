<?php

namespace App\Controller;

use App\Repository\LivreRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class HomeController extends AbstractController
{
    #[Route('/', name: 'app_home')]
    public function index(LivreRepository $livreRepository): Response
    {
        // Affiche quelques livres récents ou populaires
        $livres = $livreRepository->findBy([], ['datepub' => 'DESC'], 6);

        return $this->render('home/index.html.twig', [
            'livres' => $livres,
        ]);
    }
}

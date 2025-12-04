<?php

namespace App\Controller;

use App\Repository\WishlistRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/wishlist')]
#[IsGranted('ROLE_USER')]
class WishlistController extends AbstractController
{
    #[Route('/', name: 'app_user_wishlist')]
    public function index(WishlistRepository $wishlistRepository): Response
    {
        $wishlists = $wishlistRepository->findByUtilisateur($this->getUser()->getId());

        return $this->render('user/wishlist.html.twig', [
            'wishlists' => $wishlists,
        ]);
    }
}

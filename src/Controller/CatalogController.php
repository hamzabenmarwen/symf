<?php

namespace App\Controller;

use App\Entity\Emprunt;
use App\Entity\Wishlist;
use App\Entity\Avis;
use App\Service\OverdueService;
use App\Repository\LivreRepository;
use App\Repository\AvisRepository;
use App\Repository\WishlistRepository;
use App\Repository\CategorieRepository;
use App\Repository\AuteurRepository;
use App\Repository\EditeurRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\HttpFoundation\Request;


#[Route('/catalog')]
class CatalogController extends AbstractController
{
    public function __construct(private LoggerInterface $logger) {}
    #[Route('/', name: 'app_user_catalog')]
    public function index(
        LivreRepository $livreRepository,
        CategorieRepository $categorieRepository,
        AuteurRepository $auteurRepository,
        EditeurRepository $editeurRepository,
        Request $request
    ): Response
    {
        $page = max(1, $request->query->getInt('page', 1));
        $limit = 12;
        
        // Get filter parameters
        $categorieId = $request->query->get('categorie');
        $auteurId = $request->query->get('auteur');
        $editeurId = $request->query->get('editeur');
        $year = $request->query->get('year');
        $sortBy = $request->query->get('sort', 'datepub');
        $sortOrder = $request->query->get('order', 'DESC');

        // Fetch filtered books
        $livres = $livreRepository->filterBooks(
            $categorieId ? (int)$categorieId : null,
            $auteurId ? (int)$auteurId : null,
            $editeurId ? (int)$editeurId : null,
            $year ? (int)$year : null,
            $sortBy,
            $sortOrder,
            $page,
            $limit
        );

        // Count total for pagination
        $totalCount = $livreRepository->countFilteredBooks(
            $categorieId ? (int)$categorieId : null,
            $auteurId ? (int)$auteurId : null,
            $editeurId ? (int)$editeurId : null,
            $year ? (int)$year : null
        );

        $totalPages = ceil($totalCount / $limit);

        return $this->render('user/index.html.twig', [
            'livres' => $livres,
            'currentPage' => $page,
            'totalPages' => $totalPages,
            'totalCount' => $totalCount,
            'limit' => $limit,
            'categories' => $categorieRepository->findAll(),
            'auteurs' => $auteurRepository->findAll(),
            'editeurs' => $editeurRepository->findAll(),
            'filterCategorie' => $categorieId,
            'filterAuteur' => $auteurId,
            'filterEditeur' => $editeurId,
            'filterYear' => $year,
            'sortBy' => $sortBy,
            'sortOrder' => $sortOrder,
        ]);
    }

    #[Route('/livre/{id}', name: 'app_user_livre_detail')]
    public function livreDetail(int $id, LivreRepository $livreRepository, AvisRepository $avisRepository, WishlistRepository $wishlistRepository): Response
    {
        $livre = $livreRepository->find($id);

        if (!$livre) {
            throw $this->createNotFoundException('Livre non trouvé');
        }

        $reviews = $avisRepository->findByLivre($id);
        $isInWishlist = false;

        if ($this->getUser()) {
            $wishlist = $wishlistRepository->findByUtilisateurAndLivre($this->getUser()->getId(), $id);
            $isInWishlist = $wishlist !== null;
        }

        return $this->render('user/livre_detail.html.twig', [
            'livre' => $livre,
            'reviews' => $reviews,
            'isInWishlist' => $isInWishlist,
        ]);
    }

    #[Route('/my-account', name: 'app_user_account')]
    public function myAccount(): Response
    {
        return $this->render('user/my_account.html.twig');
    }


    #[Route('/livre/{id}/emprunter', name: 'app_livre_emprunter', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function emprunter(
        int $id,
        LivreRepository $livreRepository,
        EntityManagerInterface $em,
        OverdueService $overdueService
    ): Response {
        $livre = $livreRepository->find($id);

        if (!$livre) {
            throw $this->createNotFoundException('Livre non trouvé');
        }

        $user = $this->getUser();

        // 1. Stock insuffisant
        if ($livre->getQte() <= 0) {
            $this->logger->info('Borrow attempt on out-of-stock book', [
                'user' => $user->getEmail(),
                'book' => $livre->getTitre()
            ]);
            $this->addFlash('danger', 'Désolé, ce livre n\'est plus disponible en stock.');
            return $this->redirectToRoute('app_user_livre_detail', ['id' => $id]);
        }

        // 2. Vérifier les emprunts en retard
        if (!$overdueService->canBorrow($user)) {
            $overdueCount = count($overdueService->getOverdueBorrowings($user));
            $this->logger->warning('Borrow attempt with overdue books', [
                'user' => $user->getEmail(),
                'overdue_count' => $overdueCount
            ]);
            $this->addFlash(
                'danger',
                "Vous avez <strong>$overdueCount</strong> livre(s) en retard. Veuillez les rendre avant d'emprunter de nouveaux livres."
            );
            return $this->redirectToRoute('app_user_livre_detail', ['id' => $id]);
        }

        // 3. Déjà emprunté et non rendu
        $alreadyBorrowed = $em->getRepository(Emprunt::class)->findOneBy([
            'utilisateur' => $user,
            'livre' => $livre,
            'dateRetourEffective' => null,
        ]);

        if ($alreadyBorrowed) {
            $this->logger->info('Borrow attempt on already-borrowed book', [
                'user' => $user->getEmail(),
                'book' => $livre->getTitre()
            ]);
            $this->addFlash('warning', 'Vous avez déjà emprunté ce livre !');
            return $this->redirectToRoute('app_user_livre_detail', ['id' => $id]);
        }

        // 4. Création de l'emprunt
        $emprunt = new Emprunt();
        $emprunt->setUtilisateur($user);
        $emprunt->setLivre($livre);
        $emprunt->setStatus('active');

        $em->persist($emprunt);
        $livre->decrementQte();
        $em->flush();

        $this->logger->info('Book borrowed successfully', [
            'user' => $user->getEmail(),
            'book' => $livre->getTitre(),
            'due_date' => $emprunt->getDateRetourPrevue()->format('Y-m-d')
        ]);

        $this->addFlash(
            'success',
            'Félicitations ! Vous avez emprunté "<strong>' . $livre->getTitre() . '</strong>".<br>>
             À rendre avant le <strong>' . $emprunt->getDateRetourPrevue()->format('d/m/Y') . '</strong>.'
        );

        return $this->redirectToRoute('app_user_livre_detail', ['id' => $id]);
    }

    // ────────────────────────────────
    // RENDRE UN LIVRE
    // ────────────────────────────────
#[Route('/emprunt/{id}/rendre', name: 'app_emprunt_rendre', methods: ['POST'])]
#[IsGranted('ROLE_USER')]
public function rendre(
    Emprunt $emprunt, 
    EntityManagerInterface $em,
    Request $request,
    OverdueService $overdueService
): Response {
    $user = $this->getUser();

    // Validation CSRF
    if (!$this->isCsrfTokenValid('rendre-' . $emprunt->getId(), $request->request->get('_token'))) {
        throw $this->createAccessDeniedException('Token CSRF invalide');
    }

    // Sécurité : seul le propriétaire peut rendre
    if ($emprunt->getUtilisateur() !== $user) {
        throw $this->createAccessDeniedException('Vous n\'êtes pas autorisé à rendre ce livre.');
    }

    // Déjà rendu ?
    if ($emprunt->getDateRetourEffective() !== null) {
        $this->logger->warning('Return attempt on already-returned book', [
            'user' => $user->getEmail(),
            'emprunt_id' => $emprunt->getId()
        ]);
        $this->addFlash('warning', 'Ce livre a déjà été rendu.');
        return $this->redirectToRoute('app_user_emprunts');
    }

    // On rend le livre
    $isOverdue = $emprunt->getDateRetourPrevue() < new \DateTimeImmutable();
    $emprunt->setDateRetourEffective(new \DateTimeImmutable());
    $emprunt->setStatus('returned');
    $emprunt->getLivre()->incrementQte();

    $em->flush();

    $this->logger->info('Book returned successfully', [
        'user' => $user->getEmail(),
        'book' => $emprunt->getLivre()->getTitre(),
        'was_overdue' => $isOverdue,
        'days_borrowed' => $emprunt->getDateEmprunt()->diff(new \DateTimeImmutable())->days
    ]);

    $this->addFlash('success', 'Livre "<strong>' . $emprunt->getLivre()->getTitre() . '</strong>" rendu avec succès ! Merci !');
    return $this->redirectToRoute('app_user_emprunts');
}

#[Route('/livre/{id}/wishlist/add', name: 'app_livre_wishlist_add', methods: ['POST'])]
#[IsGranted('ROLE_USER')]
public function addToWishlist(
    int $id,
    LivreRepository $livreRepository,
    EntityManagerInterface $em,
    Request $request
): Response {
    if (!$this->isCsrfTokenValid('wishlist-' . $id, $request->request->get('_token'))) {
        throw $this->createAccessDeniedException('Token CSRF invalide');
    }

    $livre = $livreRepository->find($id);
    if (!$livre) {
        throw $this->createNotFoundException('Livre non trouvé');
    }

    $user = $this->getUser();
    $wishlistRepo = $em->getRepository(Wishlist::class);

    // Check if already in wishlist
    $existing = $wishlistRepo->findOneBy(['utilisateur' => $user, 'livre' => $livre]);
    if ($existing) {
        $this->addFlash('warning', 'Ce livre est déjà dans votre liste de souhaits.');
        return $this->redirectToRoute('app_user_livre_detail', ['id' => $id]);
    }

    $wishlist = new Wishlist();
    $wishlist->setUtilisateur($user);
    $wishlist->setLivre($livre);

    $em->persist($wishlist);
    $em->flush();

    $this->addFlash('success', 'Livre "<strong>' . $livre->getTitre() . '</strong>" ajouté à votre liste de souhaits !');
    return $this->redirectToRoute('app_user_livre_detail', ['id' => $id]);
}

#[Route('/livre/{id}/wishlist/remove', name: 'app_livre_wishlist_remove', methods: ['POST'])]
#[IsGranted('ROLE_USER')]
public function removeFromWishlist(
    int $id,
    LivreRepository $livreRepository,
    EntityManagerInterface $em,
    Request $request
): Response {
    if (!$this->isCsrfTokenValid('wishlist-remove-' . $id, $request->request->get('_token'))) {
        throw $this->createAccessDeniedException('Token CSRF invalide');
    }

    $livre = $livreRepository->find($id);
    if (!$livre) {
        throw $this->createNotFoundException('Livre non trouvé');
    }

    $user = $this->getUser();
    $wishlistRepo = $em->getRepository(Wishlist::class);
    $wishlist = $wishlistRepo->findOneBy(['utilisateur' => $user, 'livre' => $livre]);

    if (!$wishlist) {
        $this->addFlash('warning', 'Ce livre n\'est pas dans votre liste de souhaits.');
        return $this->redirectToRoute('app_user_livre_detail', ['id' => $id]);
    }

    $em->remove($wishlist);
    $em->flush();

    $this->addFlash('success', 'Livre supprimé de votre liste de souhaits.');
    return $this->redirectToRoute('app_user_livre_detail', ['id' => $id]);
}

#[Route('/livre/{id}/avis/add', name: 'app_livre_avis_add', methods: ['POST'])]
#[IsGranted('ROLE_USER')]
public function addReview(
    int $id,
    LivreRepository $livreRepository,
    AvisRepository $avisRepository,
    EntityManagerInterface $em,
    Request $request
): Response {
    // Debug: dump all form data
    dump('Form data:', $request->request->all());
    
    if (!$this->isCsrfTokenValid('avis-' . $id, $request->request->get('_token'))) {
        throw $this->createAccessDeniedException('Token CSRF invalide');
    }

    $livre = $livreRepository->find($id);
    if (!$livre) {
        throw $this->createNotFoundException('Livre non trouvé');
    }

    $user = $this->getUser();
    
    // Get rating - IMPORTANT: use get() not request->get() for POST data
    $rating = (int) $request->request->get('rating', 5);
    $comment = trim($request->request->get('comment', ''));
    
    // Debug: show the rating value
    dump('Rating value received:', $rating);
    dump('Comment:', $comment);

    // Validate rating
    if ($rating < 1 || $rating > 5) {
        $this->addFlash('danger', 'Note invalide. Veuillez choisir entre 1 et 5.');
        return $this->redirectToRoute('app_user_livre_detail', ['id' => $id]);
    }

    // Check if user already has a review for this book
    $existingReview = $em->getRepository(Avis::class)->findOneBy([
        'livre' => $livre,
        'utilisateur' => $user
    ]);

    if ($existingReview) {
        // Update existing review
        $existingReview->setRating($rating);
        $existingReview->setComment($comment ?: null);
        $avis = $existingReview;
    } else {
        // Create new review
        $avis = new Avis();
        $avis->setLivre($livre);
        $avis->setUtilisateur($user);
        $avis->setRating($rating);
        $avis->setComment($comment ?: null);
        $em->persist($avis);
    }

    // Update book's average rating
    $avgRating = $avisRepository->getAverageRatingForBook($id);
    $ratingCount = $avisRepository->getCountByBook($id);
    $livre->setAverageRating($avgRating);
    $livre->setRatingCount($ratingCount);

    $em->flush();

    $this->addFlash('success', 'Votre avis a été enregistré avec succès !');
    return $this->redirectToRoute('app_user_livre_detail', ['id' => $id]);
}

#[Route('/recherche', name: 'app_catalog_search')]
public function search(Request $request, LivreRepository $livreRepository): Response
{
    $query = $request->query->get('q', '');
    $resultats = [];
    
    if ($query) {
        $resultats = $livreRepository->search($query);
    }
    
    return $this->render('user/search.html.twig', [
        'query' => $query,
        'resultats' => $resultats,
    ]);
}
}
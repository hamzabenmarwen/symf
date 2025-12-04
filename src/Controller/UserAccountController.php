<?php

namespace App\Controller;

use App\Entity\Emprunt;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/my-account')]
#[IsGranted('ROLE_USER')]
class UserAccountController extends AbstractController
{
    public function __construct(private LoggerInterface $logger) {}
    #[Route('/', name: 'app_user_account')]
    public function index(): Response
    {
        $user = $this->getUser();

        return $this->render('user_account/index.html.twig', [
            'user' => $user,
        ]);
    }

    #[Route('/emprunts', name: 'app_user_emprunts')]
    public function emprunts(EntityManagerInterface $em): Response
    {
        $user = $this->getUser();
        
        // Emprunts en cours (non rendus)
        $empruntsEnCours = $em->getRepository(Emprunt::class)->findBy([
            'utilisateur' => $user,
            'dateRetourEffective' => null
        ], [
            'dateEmprunt' => 'DESC'
        ]);
        
        // Historique (rendus)
        $historique = $em->getRepository(Emprunt::class)->createQueryBuilder('e')
            ->where('e.utilisateur = :user')
            ->andWhere('e.dateRetourEffective IS NOT NULL')
            ->setParameter('user', $user)
            ->orderBy('e.dateRetourEffective', 'DESC')
            ->setMaxResults(20) // Limite aux 20 derniers
            ->getQuery()
            ->getResult();

        return $this->render('user_account/emprunts.html.twig', [
            'empruntsEnCours' => $empruntsEnCours,
            'historique' => $historique,
        ]);
    }

    #[Route('/emprunt/{id}/rendre', name: 'app_user_emprunt_rendre', methods: ['POST'])]
    public function rendreEmprunt(
        Emprunt $emprunt, 
        EntityManagerInterface $em,
        Request $request
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
            $this->logger->warning('Return attempt on already-returned book (user account)', [
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

        $this->logger->info('Book returned from user account', [
            'user' => $user->getEmail(),
            'book' => $emprunt->getLivre()->getTitre(),
            'was_overdue' => $isOverdue,
            'days_borrowed' => $emprunt->getDateEmprunt()->diff(new \DateTimeImmutable())->days
        ]);

        $this->addFlash('success', 'Livre "<strong>' . $emprunt->getLivre()->getTitre() . '</strong>" rendu avec succès ! Merci !');
        return $this->redirectToRoute('app_user_emprunts');
    }
}
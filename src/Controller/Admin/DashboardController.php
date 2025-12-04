<?php

namespace App\Controller\Admin;

use App\Entity\Emprunt;
use App\Repository\EmpruntRepository;
use App\Service\AdminStatisticsService;
use EasyCorp\Bundle\EasyAdminBundle\Attribute\AdminDashboard;
use EasyCorp\Bundle\EasyAdminBundle\Config\Dashboard;
use EasyCorp\Bundle\EasyAdminBundle\Config\MenuItem;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractDashboardController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use App\Entity\Auteur;
use App\Entity\Editeur;
use App\Entity\Categorie;
use App\Entity\Livre;
use App\Entity\Utilisateur;

#[IsGranted('ROLE_ADMIN')]
#[AdminDashboard(routePath: '/admin', routeName: 'admin')]
class DashboardController extends AbstractDashboardController
{
    public function __construct(
        private AdminStatisticsService $statisticsService,
        private EmpruntRepository $empruntRepository
    ) {}

    public function index(): Response
    {
        $stats = $this->statisticsService->getDashboardStats();
        $borrowingTrends = $this->statisticsService->getBorrowingTrends();
        $returnRate = $this->statisticsService->getReturnRate();
        
        return $this->render('admin/dashboard.html.twig', [
            'stats' => $stats,
            'trends' => $borrowingTrends,
            'returnRate' => $returnRate,
        ]);
    }

    #[Route('/admin/emprunts', name: 'admin_emprunts')]
    public function borrowings(): Response
    {
        $emprunts = $this->empruntRepository->findBy([], ['dateEmprunt' => 'DESC']);
        
        return $this->render('admin/emprunts_list.html.twig', [
            'emprunts' => $emprunts,
        ]);
    }

    public function configureDashboard(): Dashboard
    {
        return Dashboard::new()
            ->setTitle('Bibliothèque - Dashboard');
    }

    public function configureMenuItems(): iterable
    {
        yield MenuItem::linkToDashboard('Dashboard', 'fa fa-home');
        yield MenuItem::linkToCrud('Livres', 'fas fa-book', Livre::class);
        yield MenuItem::linkToCrud('Utilisateurs', 'fas fa-users', Utilisateur::class);
        yield MenuItem::linkToCrud('Auteurs', 'fas fa-user', Auteur::class);
        yield MenuItem::linkToCrud('Éditeurs', 'fas fa-building', Editeur::class);
        yield MenuItem::linkToCrud('Catégories', 'fas fa-tags', Categorie::class);
        yield MenuItem::linkToRoute('Emprunts', 'fas fa-exchange', 'admin_emprunts');
        yield MenuItem::linkToLogout('Déconnexion', 'fa fa-sign-out');
    }
}
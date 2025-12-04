<?php

namespace App\Service;

use App\Entity\Livre;
use App\Entity\Utilisateur;
use App\Entity\Emprunt;
use App\Entity\Avis;
use App\Repository\EmpruntRepository;
use App\Repository\LivreRepository;
use App\Repository\UtilisateurRepository;
use App\Repository\AvisRepository;
use Doctrine\ORM\EntityManagerInterface;

class AdminStatisticsService
{
    public function __construct(
        private EmpruntRepository $empruntRepository,
        private LivreRepository $livreRepository,
        private UtilisateurRepository $utilisateurRepository,
        private AvisRepository $avisRepository,
        private OverdueService $overdueService,
        private EntityManagerInterface $em
    ) {}

    /**
     * Get complete dashboard statistics
     */
    public function getDashboardStats(): array
    {
        return [
            'books' => $this->getBookStatistics(),
            'users' => $this->getUserStatistics(),
            'borrowings' => $this->getBorrowingStatistics(),
            'reviews' => $this->getReviewStatistics(),
            'topBooks' => $this->getTopBooks(),
            'topUsers' => $this->getTopUsers(),
            'recentBorrowings' => $this->getRecentBorrowings(10),
        ];
    }

    /**
     * Get book statistics
     */
    public function getBookStatistics(): array
    {
        $totalBooks = $this->livreRepository->count([]);
        $availableBooks = $this->em->getRepository(Livre::class)
            ->createQueryBuilder('l')
            ->where('l.qte > 0')
            ->select('COUNT(l.id)')
            ->getQuery()
            ->getSingleScalarResult();

        return [
            'total' => $totalBooks,
            'available' => (int)$availableBooks,
            'unavailable' => $totalBooks - (int)$availableBooks,
        ];
    }

    /**
     * Get user statistics
     */
    public function getUserStatistics(): array
    {
        $totalUsers = $this->utilisateurRepository->count([]);
        $adminUsers = $this->em->getRepository(Utilisateur::class)
            ->createQueryBuilder('u')
            ->where('u.roles LIKE :role')
            ->setParameter('role', '%ROLE_ADMIN%')
            ->select('COUNT(u.id)')
            ->getQuery()
            ->getSingleScalarResult();

        return [
            'total' => $totalUsers,
            'admins' => (int)$adminUsers,
            'regularUsers' => $totalUsers - (int)$adminUsers,
        ];
    }

    /**
     * Get borrowing statistics
     */
    public function getBorrowingStatistics(): array
    {
        $totalBorrowings = $this->empruntRepository->count([]);
        $activeBorrowings = $this->em->getRepository(Emprunt::class)
            ->createQueryBuilder('e')
            ->where('e.dateRetourEffective IS NULL')
            ->select('COUNT(e.id)')
            ->getQuery()
            ->getSingleScalarResult();

        $overdueCount = $this->overdueService->countAllOverdue();
        $completedBorrowings = $totalBorrowings - (int)$activeBorrowings;

        return [
            'total' => $totalBorrowings,
            'active' => (int)$activeBorrowings,
            'overdue' => $overdueCount,
            'completed' => $completedBorrowings,
            'borrowingRate' => $totalBorrowings > 0 ? round(((int)$activeBorrowings / $totalBorrowings) * 100, 2) : 0,
        ];
    }

    /**
     * Get review statistics
     */
    public function getReviewStatistics(): array
    {
        $totalReviews = $this->avisRepository->count([]);
        $avgRating = 0;

        if ($totalReviews > 0) {
            $result = $this->em->getRepository(Avis::class)
                ->createQueryBuilder('a')
                ->select('AVG(a.rating) as avgRating')
                ->getQuery()
                ->getOneOrNullResult();
            
            $avgRating = $result ? round($result['avgRating'], 2) : 0;
        }

        return [
            'total' => $totalReviews,
            'averageRating' => $avgRating,
            '5star' => $this->getReviewCountByRating(5),
            '4star' => $this->getReviewCountByRating(4),
            '3star' => $this->getReviewCountByRating(3),
            '2star' => $this->getReviewCountByRating(2),
            '1star' => $this->getReviewCountByRating(1),
        ];
    }

    /**
     * Get top borrowed books
     */
    public function getTopBooks(int $limit = 5): array
    {
        return $this->em->getRepository(Livre::class)
            ->createQueryBuilder('l')
            ->leftJoin('l.emprunts', 'e')
            ->select('l.id', 'l.titre', 'COUNT(e.id) as borrowCount')
            ->groupBy('l.id')
            ->orderBy('borrowCount', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Get most active users
     */
    public function getTopUsers(int $limit = 5): array
    {
        return $this->em->getRepository(Utilisateur::class)
            ->createQueryBuilder('u')
            ->leftJoin('u.emprunts', 'e')
            ->select('u.id', 'u.email', 'u.firstName', 'u.lastName', 'COUNT(e.id) as borrowCount')
            ->groupBy('u.id')
            ->orderBy('borrowCount', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Get recent borrowings
     */
    public function getRecentBorrowings(int $limit = 10): array
    {
        return $this->empruntRepository->findBy(
            [],
            ['dateEmprunt' => 'DESC'],
            $limit
        );
    }

    /**
     * Get review count by rating
     */
    private function getReviewCountByRating(int $rating): int
    {
        return (int)$this->em->getRepository(Avis::class)
            ->createQueryBuilder('a')
            ->where('a.rating = :rating')
            ->setParameter('rating', $rating)
            ->select('COUNT(a.id)')
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Get borrowing trends (last 7 days)
     */
    public function getBorrowingTrends(): array
    {
        // Get all borrowings from the last 7 days
        $sevenDaysAgo = new \DateTimeImmutable('-7 days');
        
        $borrowings = $this->empruntRepository
            ->createQueryBuilder('e')
            ->where('e.dateEmprunt >= :sevenDaysAgo')
            ->setParameter('sevenDaysAgo', $sevenDaysAgo)
            ->getQuery()
            ->getResult();

        // Initialize data structure for last 7 days
        $data = [];
        for ($i = 6; $i >= 0; $i--) {
            $date = (new \DateTimeImmutable())->modify("-$i days")->format('Y-m-d');
            $data[$date] = 0;
        }

        // Group by date in PHP
        foreach ($borrowings as $borrowing) {
            $dateStr = $borrowing->getDateEmprunt()->format('Y-m-d');
            if (isset($data[$dateStr])) {
                $data[$dateStr]++;
            }
        }

        return $data;
    }

    /**
     * Get return rate (% of books returned on time)
     */
    public function getReturnRate(): array
    {
        $completedBorrowings = $this->em->getRepository(Emprunt::class)
            ->createQueryBuilder('e')
            ->where('e.dateRetourEffective IS NOT NULL')
            ->getQuery()
            ->getResult();

        $onTime = 0;
        $late = 0;

        foreach ($completedBorrowings as $borrowing) {
            if ($borrowing->getDateRetourEffective() <= $borrowing->getDateRetourPrevue()) {
                $onTime++;
            } else {
                $late++;
            }
        }

        $total = $onTime + $late;
        $onTimePercentage = $total > 0 ? round(($onTime / $total) * 100, 2) : 0;
        $latePercentage = $total > 0 ? round(($late / $total) * 100, 2) : 0;

        return [
            'total' => $total,
            'onTime' => $onTime,
            'late' => $late,
            'onTimePercentage' => $onTimePercentage,
            'latePercentage' => $latePercentage,
        ];
    }
}

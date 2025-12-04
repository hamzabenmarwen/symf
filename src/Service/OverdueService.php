<?php

namespace App\Service;

use App\Entity\Emprunt;
use App\Entity\Utilisateur;
use App\Repository\EmpruntRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;

class OverdueService
{
    public function __construct(
        private EmpruntRepository $empruntRepository,
        private EntityManagerInterface $em,
        private LoggerInterface $logger
    ) {}

    /**
     * Check if a borrowing is overdue and update its status
     */
    public function markOverdueIfNeeded(Emprunt $emprunt): void
    {
        // Already returned
        if ($emprunt->getDateRetourEffective() !== null) {
            $emprunt->setStatus('returned');
            return;
        }

        // Check if due date has passed
        if ($emprunt->getDateRetourPrevue() < new \DateTimeImmutable()) {
            $oldStatus = $emprunt->getStatus();
            $emprunt->setStatus('overdue');
            $this->logger->warning('Book marked overdue', [
                'emprunt_id' => $emprunt->getId(),
                'user' => $emprunt->getUtilisateur()->getEmail(),
                'book' => $emprunt->getLivre()->getTitre(),
                'due_date' => $emprunt->getDateRetourPrevue()->format('Y-m-d'),
                'previous_status' => $oldStatus
            ]);
        } else {
            $emprunt->setStatus('active');
        }

        $this->em->flush();
    }

    /**
     * Get all overdue borrowings for a user
     */
    public function getOverdueBorrowings(Utilisateur $user): array
    {
        $now = new \DateTimeImmutable();
        $borrowings = $this->empruntRepository->findBy([
            'utilisateur' => $user,
            'dateRetourEffective' => null
        ]);

        return array_filter($borrowings, function (Emprunt $e) use ($now) {
            return $e->getDateRetourPrevue() < $now;
        });
    }

    /**
     * Check if user can borrow (no overdue books)
     */
    public function canBorrow(Utilisateur $user): bool
    {
        return count($this->getOverdueBorrowings($user)) === 0;
    }

    /**
     * Get borrowings due soon (within 3 days)
     */
    public function getBorrowingsDueSoon(Utilisateur $user, int $daysUntilDue = 3): array
    {
        $now = new \DateTimeImmutable();
        $futureDateLimit = $now->modify("+$daysUntilDue days");

        $borrowings = $this->empruntRepository->findBy([
            'utilisateur' => $user,
            'dateRetourEffective' => null
        ]);

        return array_filter($borrowings, function (Emprunt $e) use ($now, $futureDateLimit) {
            $dueDate = $e->getDateRetourPrevue();
            return $dueDate >= $now && $dueDate <= $futureDateLimit;
        });
    }

    /**
     * Get count of overdue borrowings across all users
     */
    public function countAllOverdue(): int
    {
        $qb = $this->empruntRepository->createQueryBuilder('e');
        $now = new \DateTimeImmutable();

        $result = $qb
            ->where('e.dateRetourEffective IS NULL')
            ->andWhere('e.dateRetourPrevue < :now')
            ->setParameter('now', $now)
            ->select('COUNT(e.id)')
            ->getQuery()
            ->getSingleScalarResult();

        return (int) $result;
    }

    /**
     * Get all currently overdue borrowings (across all users)
     */
    public function getAllOverdueBooks(): array
    {
        $qb = $this->empruntRepository->createQueryBuilder('e');
        $now = new \DateTimeImmutable();

        return $qb
            ->where('e.dateRetourEffective IS NULL')
            ->andWhere('e.dateRetourPrevue < :now')
            ->setParameter('now', $now)
            ->orderBy('e.dateRetourPrevue', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Update status for all borrowings (typically run as scheduled task)
     */
    public function updateAllBorrowingStatuses(): int
    {
        $borrowings = $this->empruntRepository->findAll();
        $updated = 0;

        foreach ($borrowings as $borrowing) {
            $oldStatus = $borrowing->getStatus();
            $this->markOverdueIfNeeded($borrowing);
            
            if ($borrowing->getStatus() !== $oldStatus) {
                $updated++;
            }
        }

        $this->logger->info('Completed batch overdue status update', [
            'total_borrowings' => count($borrowings),
            'updated_count' => $updated
        ]);

        return $updated;
    }

    /**
     * Get days remaining until return date
     */
    public function getDaysRemaining(Emprunt $emprunt): int
    {
        if ($emprunt->getDateRetourEffective() !== null) {
            return 0;
        }

        $now = new \DateTimeImmutable('today');
        $daysRemaining = $now->diff($emprunt->getDateRetourPrevue())->days;

        if ($now > $emprunt->getDateRetourPrevue()) {
            return -$daysRemaining; // Negative for overdue
        }

        return $daysRemaining;
    }
}

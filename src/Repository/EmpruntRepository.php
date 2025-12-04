<?php

namespace App\Repository;

use App\Entity\Emprunt;
use App\Entity\Utilisateur;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Emprunt>
 */
class EmpruntRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Emprunt::class);
    }

    /**
     * Find all active (not returned) borrowings
     */
    public function findActive(): array
    {
        return $this->createQueryBuilder('e')
            ->where('e.dateRetourEffective IS NULL')
            ->orderBy('e.dateRetourPrevue', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find overdue borrowings
     */
    public function findOverdue(): array
    {
        return $this->createQueryBuilder('e')
            ->where('e.dateRetourEffective IS NULL')
            ->andWhere('e.dateRetourPrevue < :now')
            ->setParameter('now', new \DateTimeImmutable('today'))
            ->orderBy('e.dateRetourPrevue', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find borrowings by user
     */
    public function findByUser(Utilisateur $user): array
    {
        return $this->createQueryBuilder('e')
            ->where('e.utilisateur = :user')
            ->setParameter('user', $user)
            ->orderBy('e.dateEmprunt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find active borrowings by user
     */
    public function findActiveByUser(Utilisateur $user): array
    {
        return $this->createQueryBuilder('e')
            ->where('e.utilisateur = :user')
            ->andWhere('e.dateRetourEffective IS NULL')
            ->setParameter('user', $user)
            ->orderBy('e.dateRetourPrevue', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find overdue borrowings by user
     */
    public function findOverdueByUser(Utilisateur $user): array
    {
        return $this->createQueryBuilder('e')
            ->where('e.utilisateur = :user')
            ->andWhere('e.dateRetourEffective IS NULL')
            ->andWhere('e.dateRetourPrevue < :now')
            ->setParameter('user', $user)
            ->setParameter('now', new \DateTimeImmutable('today'))
            ->getQuery()
            ->getResult();
    }

    /**
     * Count active borrowings
     */
    public function countActive(): int
    {
        return (int)$this->createQueryBuilder('e')
            ->select('COUNT(e.id)')
            ->where('e.dateRetourEffective IS NULL')
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Find borrowings within date range
     */
    public function findByDateRange(\DateTimeImmutable $start, \DateTimeImmutable $end): array
    {
        return $this->createQueryBuilder('e')
            ->where('e.dateEmprunt >= :start')
            ->andWhere('e.dateEmprunt <= :end')
            ->setParameter('start', $start)
            ->setParameter('end', $end)
            ->orderBy('e.dateEmprunt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find recently returned borrowings
     */
    public function findRecentlyReturned(int $limit = 10): array
    {
        return $this->createQueryBuilder('e')
            ->where('e.dateRetourEffective IS NOT NULL')
            ->orderBy('e.dateRetourEffective', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Find most borrowed books
     */
    public function findMostBorrowedBooks(int $limit = 10): array
    {
        return $this->createQueryBuilder('e')
            ->select('l.id, l.titre, COUNT(e.id) as borrowCount')
            ->innerJoin('e.livre', 'l')
            ->groupBy('l.id')
            ->orderBy('borrowCount', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }
}


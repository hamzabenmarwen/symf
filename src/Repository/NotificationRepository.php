<?php

namespace App\Repository;

use App\Entity\Notification;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Notification>
 */
class NotificationRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Notification::class);
    }

    public function findUnreadByUtilisateur(int $utilisateurId, int $limit = 10): array
    {
        return $this->createQueryBuilder('n')
            ->where('n.utilisateur = :userid')
            ->andWhere('n.readAt IS NULL')
            ->setParameter('userid', $utilisateurId)
            ->orderBy('n.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    public function countUnreadByUtilisateur(int $utilisateurId): int
    {
        return (int) $this->createQueryBuilder('n')
            ->select('COUNT(n.id)')
            ->where('n.utilisateur = :userid')
            ->andWhere('n.readAt IS NULL')
            ->setParameter('userid', $utilisateurId)
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function findByUtilisateur(int $utilisateurId, int $limit = 50): array
    {
        return $this->createQueryBuilder('n')
            ->where('n.utilisateur = :userid')
            ->setParameter('userid', $utilisateurId)
            ->orderBy('n.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }
}

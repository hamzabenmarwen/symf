<?php

namespace App\Repository;

use App\Entity\Avis;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Avis>
 */
class AvisRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Avis::class);
    }

    public function findByLivre(int $livreId, int $limit = 10): array
    {
        return $this->createQueryBuilder('a')
            ->leftJoin('a.utilisateur', 'u')
            ->where('a.livre = :livreid')
            ->setParameter('livreid', $livreId)
            ->orderBy('a.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    public function getAverageRatingForBook(int $livreId): ?float
    {
        $result = $this->createQueryBuilder('a')
            ->select('AVG(a.rating) as average')
            ->where('a.livre = :livreid')
            ->setParameter('livreid', $livreId)
            ->getQuery()
            ->getOneOrNullResult();

        return $result ? (float) $result['average'] : null;
    }

    public function getCountByBook(int $livreId): int
    {
        return (int) $this->createQueryBuilder('a')
            ->select('COUNT(a.id)')
            ->where('a.livre = :livreid')
            ->setParameter('livreid', $livreId)
            ->getQuery()
            ->getSingleScalarResult();
    }
}

<?php

namespace App\Repository;

use App\Entity\Reservation;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Reservation>
 */
class ReservationRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Reservation::class);
    }

    public function findByUtilisateur(int $utilisateurId): array
    {
        return $this->createQueryBuilder('r')
            ->leftJoin('r.livre', 'l')
            ->where('r.utilisateur = :userid')
            ->andWhere('r.status = :status')
            ->setParameter('userid', $utilisateurId)
            ->setParameter('status', 'pending')
            ->orderBy('r.reservedAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function findByLivre(int $livreId, string $status = 'pending'): array
    {
        return $this->createQueryBuilder('r')
            ->where('r.livre = :livreid')
            ->andWhere('r.status = :status')
            ->setParameter('livreid', $livreId)
            ->setParameter('status', $status)
            ->orderBy('r.reservedAt', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function findExistingReservation(int $utilisateurId, int $livreId): ?Reservation
    {
        return $this->createQueryBuilder('r')
            ->where('r.utilisateur = :userid')
            ->andWhere('r.livre = :livreid')
            ->andWhere('r.status IN (:statuses)')
            ->setParameter('userid', $utilisateurId)
            ->setParameter('livreid', $livreId)
            ->setParameter('statuses', ['pending', 'notified'])
            ->getQuery()
            ->getOneOrNullResult();
    }
}

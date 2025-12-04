<?php

namespace App\Repository;

use App\Entity\Wishlist;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Wishlist>
 */
class WishlistRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Wishlist::class);
    }

    public function findByUtilisateur(int $utilisateurId): array
    {
        return $this->createQueryBuilder('w')
            ->leftJoin('w.livre', 'l')
            ->where('w.utilisateur = :userid')
            ->setParameter('userid', $utilisateurId)
            ->orderBy('w.addedAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function findByUtilisateurAndLivre(int $utilisateurId, int $livreId): ?Wishlist
    {
        return $this->createQueryBuilder('w')
            ->where('w.utilisateur = :userid')
            ->andWhere('w.livre = :livreid')
            ->setParameter('userid', $utilisateurId)
            ->setParameter('livreid', $livreId)
            ->getQuery()
            ->getOneOrNullResult();
    }
}

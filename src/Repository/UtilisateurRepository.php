<?php

namespace App\Repository;

use App\Entity\Utilisateur;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Security\Core\Exception\UnsupportedUserException;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\PasswordUpgraderInterface;

/**
 * @extends ServiceEntityRepository<Utilisateur>
 */
class UtilisateurRepository extends ServiceEntityRepository implements PasswordUpgraderInterface
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Utilisateur::class);
    }

    /**
     * Used to upgrade (rehash) the user's password automatically over time.
     */
    public function upgradePassword(PasswordAuthenticatedUserInterface $user, string $newHashedPassword): void
    {
        if (!$user instanceof Utilisateur) {
            throw new UnsupportedUserException(sprintf('Instances of "%s" are not supported.', $user::class));
        }

        $user->setPassword($newHashedPassword);
        $this->getEntityManager()->persist($user);
        $this->getEntityManager()->flush();
    }

    /**
     * Find users by search term (email, first name, last name)
     */
    public function findBySearchTerm(string $term): array
    {
        return $this->createQueryBuilder('u')
            ->where('u.email LIKE :term')
            ->orWhere('u.firstName LIKE :term')
            ->orWhere('u.lastName LIKE :term')
            ->setParameter('term', '%' . $term . '%')
            ->orderBy('u.email', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find admin users
     */
    public function findAdmins(): array
    {
        return $this->createQueryBuilder('u')
            ->where('u.roles LIKE :role')
            ->setParameter('role', '%ROLE_ADMIN%')
            ->orderBy('u.email', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find regular users (non-admin)
     */
    public function findRegularUsers(): array
    {
        return $this->createQueryBuilder('u')
            ->where('u.roles NOT LIKE :role')
            ->setParameter('role', '%ROLE_ADMIN%')
            ->orderBy('u.email', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find most active users (by borrowing count)
     */
    public function findMostActive(int $limit = 10): array
    {
        return $this->createQueryBuilder('u')
            ->select('u, COUNT(e.id) as borrowCount')
            ->leftJoin('u.emprunts', 'e')
            ->groupBy('u.id')
            ->orderBy('borrowCount', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Find users by creation date range
     */
    public function findByCreatedDateRange(\DateTimeImmutable $start, \DateTimeImmutable $end): array
    {
        return $this->createQueryBuilder('u')
            ->where('u.createdAt >= :start')
            ->andWhere('u.createdAt <= :end')
            ->setParameter('start', $start)
            ->setParameter('end', $end)
            ->orderBy('u.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Count users by role
     */
    public function countAdmins(): int
    {
        return (int)$this->createQueryBuilder('u')
            ->select('COUNT(u.id)')
            ->where('u.roles LIKE :role')
            ->setParameter('role', '%ROLE_ADMIN%')
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Find users with most reviews
     */
    public function findMostReviewers(int $limit = 10): array
    {
        return $this->createQueryBuilder('u')
            ->select('u, COUNT(av.id) as reviewCount')
            ->leftJoin('u.avis', 'av')
            ->groupBy('u.id')
            ->orderBy('reviewCount', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Find users with wishlist items
     */
    public function findWithWishlist(): array
    {
        return $this->createQueryBuilder('u')
            ->innerJoin('u.wishlists', 'w')
            ->distinct(true)
            ->orderBy('u.email', 'ASC')
            ->getQuery()
            ->getResult();
    }
}

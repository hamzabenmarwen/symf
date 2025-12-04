<?php

namespace App\Repository;

use App\Entity\Livre;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Knp\Component\Pager\Paginator;
use Knp\Component\Pager\PaginatorInterface;

/**
 * @extends ServiceEntityRepository<Livre>
 */
class LivreRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Livre::class);
    }

    /**
     * Find books by search term (title, ISBN, author)
     */
    public function findBySearchTerm(string $term): array
    {
        return $this->createQueryBuilder('l')
            ->where('l.titre LIKE :term')
            ->orWhere('l.isbn LIKE :term')
            ->orWhere('CONCAT(a.nom, \' \', a.prenom) LIKE :term')
            ->leftJoin('l.auteurs', 'a')
            ->setParameter('term', '%' . $term . '%')
            ->orderBy('l.titre', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find books by category
     */
    public function findByCategory($category): array
    {
        return $this->createQueryBuilder('l')
            ->where('l.no = :category')
            ->setParameter('category', $category)
            ->orderBy('l.titre', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find books by author
     */
    public function findByAuthor($author): array
    {
        return $this->createQueryBuilder('l')
            ->innerJoin('l.auteurs', 'a')
            ->where('a = :author')
            ->setParameter('author', $author)
            ->orderBy('l.titre', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find available books (quantity > 0)
     */
    public function findAvailable(): array
    {
        return $this->createQueryBuilder('l')
            ->where('l.qte > 0')
            ->orderBy('l.titre', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find out of stock books
     */
    public function findOutOfStock(): array
    {
        return $this->createQueryBuilder('l')
            ->where('l.qte <= 0')
            ->orderBy('l.titre', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find low stock books (quantity < threshold)
     */
    public function findLowStock(int $threshold = 3): array
    {
        return $this->createQueryBuilder('l')
            ->where('l.qte <= :threshold')
            ->andWhere('l.qte > 0')
            ->setParameter('threshold', $threshold)
            ->orderBy('l.qte', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find books by price range
     */
    public function findByPriceRange(float $minPrice, float $maxPrice): array
    {
        return $this->createQueryBuilder('l')
            ->where('l.prixunitaire >= :minPrice')
            ->andWhere('l.prixunitaire <= :maxPrice')
            ->setParameter('minPrice', $minPrice)
            ->setParameter('maxPrice', $maxPrice)
            ->orderBy('l.prixunitaire', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find recent books
     */
    public function findRecent(int $limit = 10): array
    {
        return $this->createQueryBuilder('l')
            ->orderBy('l.datepub', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Find books with most reviews
     */
    public function findMostReviewed(int $limit = 10): array
    {
        return $this->createQueryBuilder('l')
            ->select('l, COUNT(av.id) as reviewCount')
            ->leftJoin('l.avis', 'av')
            ->groupBy('l.id')
            ->orderBy('reviewCount', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }    public function search(string $query): array
    {
        return $this->createQueryBuilder('l')
            ->leftJoin('l.auteurs', 'a')
            ->leftJoin('l.editeur', 'e')
            ->leftJoin('l.no', 'c')
            ->where('l.titre LIKE :query')
            ->orWhere('a.nom LIKE :query')
            ->orWhere('l.isbn LIKE :query')
            ->orWhere('e.nom LIKE :query')
            ->orWhere('c.designation LIKE :query')
            ->setParameter('query', '%' . $query . '%')
            ->distinct()
            ->orderBy('l.titre', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Get all books with pagination
     */
    public function getPaginatedBooks(int $page = 1, int $limit = 12): array
    {
        $qb = $this->createQueryBuilder('l')
            ->orderBy('l.datepub', 'DESC')
            ->setFirstResult(($page - 1) * $limit)
            ->setMaxResults($limit);

        return $qb->getQuery()->getResult();
    }

    /**
     * Get total count of books
     */
    public function getTotalCount(): int
    {
        return (int) $this->createQueryBuilder('l')
            ->select('COUNT(l.id)')
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Filter books by multiple criteria
     */
    public function filterBooks(?int $categorieId = null, ?int $auteurId = null, ?int $editeurId = null, ?int $year = null, string $sortBy = 'datepub', string $sortOrder = 'DESC', int $page = 1, int $limit = 12): array
    {
        $qb = $this->createQueryBuilder('l');

        if ($categorieId) {
            $qb->andWhere('l.no = :categorie')
                ->setParameter('categorie', $categorieId);
        }

        if ($auteurId) {
            $qb->leftJoin('l.auteurs', 'a')
                ->andWhere('a.id = :auteur')
                ->setParameter('auteur', $auteurId);
        }

        if ($editeurId) {
            $qb->andWhere('l.editeur = :editeur')
                ->setParameter('editeur', $editeurId);
        }

        if ($year) {
            $qb->andWhere('YEAR(l.datepub) = :year')
                ->setParameter('year', $year);
        }

        // Validate sortBy to prevent SQL injection
        $allowedSorts = ['datepub', 'titre', 'prixunitaire', 'averageRating'];
        if (!in_array($sortBy, $allowedSorts)) {
            $sortBy = 'datepub';
        }

        $sortOrder = strtoupper($sortOrder) === 'ASC' ? 'ASC' : 'DESC';

        $qb->orderBy('l.' . $sortBy, $sortOrder)
            ->setFirstResult(($page - 1) * $limit)
            ->setMaxResults($limit)
            ->distinct();

        return $qb->getQuery()->getResult();
    }

    /**
     * Count filtered books
     */
    public function countFilteredBooks(?int $categorieId = null, ?int $auteurId = null, ?int $editeurId = null, ?int $year = null): int
    {
        $qb = $this->createQueryBuilder('l')
            ->select('COUNT(DISTINCT l.id)');

        if ($categorieId) {
            $qb->andWhere('l.no = :categorie')
                ->setParameter('categorie', $categorieId);
        }

        if ($auteurId) {
            $qb->leftJoin('l.auteurs', 'a')
                ->andWhere('a.id = :auteur')
                ->setParameter('auteur', $auteurId);
        }

        if ($editeurId) {
            $qb->andWhere('l.editeur = :editeur')
                ->setParameter('editeur', $editeurId);
        }

        if ($year) {
            $qb->andWhere('YEAR(l.datepub) = :year')
                ->setParameter('year', $year);
        }

        return (int) $qb->getQuery()->getSingleScalarResult();
    }

    /**
     * Get popular books (most rated/borrowed)
     */
    public function getPopularBooks(int $limit = 6): array
    {
        return $this->createQueryBuilder('l')
            ->orderBy('l.averageRating', 'DESC')
            ->addOrderBy('l.ratingCount', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Get recently published books
     */
    public function getRecentBooks(int $limit = 6): array
    {
        return $this->createQueryBuilder('l')
            ->orderBy('l.datepub', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }
}

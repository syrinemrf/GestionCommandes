<?php

namespace App\Repository;

use App\Entity\ProductVariation;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\Tools\Pagination\Paginator;
use Doctrine\Persistence\ManagerRegistry;
use App\Entity\Product;

/**
 * @extends ServiceEntityRepository<ProductVariation>
 */
class ProductVariationRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ProductVariation::class);
    }


    /**
     * @return ProductVariation[]
     */
    public function findActiveByProduct(Product $product): array
    {
        return $this->createQueryBuilder('variation')
            ->andWhere('variation.product = :product')
            ->andWhere('variation.isDeleted = false')
            ->setParameter('product', $product)
            ->orderBy('variation.id', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function findForDatatable(
        Product $product,
        int $start,
        int $length,
        string $search,
        string $orderBy = 'variation.id',
        string $orderDirection = 'DESC',
    ): array {
        $qb = $this->createQueryBuilder('variation')
            ->andWhere('variation.product = :product')
            ->andWhere('variation.isDeleted = :deleted')
            ->setParameter('product', $product)
            ->setParameter('deleted', false);

        if ($search !== '') {
            $qb->andWhere(
                'variation.libelle LIKE :search
                OR variation.reference LIKE :search
                OR variation.attributs LIKE :search'
            )->setParameter('search', '%' . $search . '%');
        }

        $filteredQuery = clone $qb;

        $qb->orderBy($orderBy, $orderDirection)
            ->setFirstResult($start)
            ->setMaxResults($length);

        $rows = iterator_to_array(new Paginator($qb));

        $total = $this->createQueryBuilder('variation')
            ->select('COUNT(variation.id)')
            ->andWhere('variation.product = :product')
            ->andWhere('variation.isDeleted = :deleted')
            ->setParameter('product', $product)
            ->setParameter('deleted', false)
            ->getQuery()
            ->getSingleScalarResult();

        $filtered = $search === ''
            ? $total
            : $filteredQuery
                ->select('COUNT(variation.id)')
                ->getQuery()
                ->getSingleScalarResult();

        return [
            'rows' => $rows,
            'filtered' => (int) $filtered,
            'total' => (int) $total,
        ];
    }

    public function getTotalStock(Product $product): int
    {
        return (int) $this->createQueryBuilder('variation')
            ->select('COALESCE(SUM(variation.stock), 0)')
            ->andWhere('variation.product = :product')
            ->andWhere('variation.isDeleted = :deleted')
            ->setParameter('product', $product)
            ->setParameter('deleted', false)
            ->getQuery()
            ->getSingleScalarResult();
    }

    
}

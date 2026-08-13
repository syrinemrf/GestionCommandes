<?php

namespace App\Repository;

use App\Entity\MouvementStock;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<MouvementStock>
 */
class MouvementStockRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, MouvementStock::class);
    }

    /**
     * @return MouvementStock[]
     */
    public function findRecentForHome(?User $fournisseur, int $limit = 5): array
    {
        $qb = $this->createQueryBuilder('mouvement')
            ->join('mouvement.variation', 'variation')
            ->addSelect('variation')
            ->join('variation.product', 'product')
            ->addSelect('product')
            ->andWhere('variation.isDeleted = :deleted')
            ->andWhere('product.isDeleted = :deleted')
            ->setParameter('deleted', false)
            ->orderBy('mouvement.createdAt', 'DESC')
            ->addOrderBy('mouvement.id', 'DESC')
            ->setMaxResults($limit);

        if ($fournisseur !== null) {
            $qb->andWhere('product.fournisseur = :fournisseur')
                ->setParameter('fournisseur', $fournisseur);
        }

        return $qb->getQuery()->getResult();
    }
}

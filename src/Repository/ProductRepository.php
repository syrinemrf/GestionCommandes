<?php

namespace App\Repository;

use App\Entity\Product;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\Tools\Pagination\Paginator;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Product>
 */
class ProductRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Product::class);
    }

    /**
     * @return Product[]
     */
    public function findRecentForHome(?User $fournisseur, int $limit = 5): array
    {
        $qb = $this->createQueryBuilder('product')
            ->andWhere('product.isDeleted = :deleted')
            ->setParameter('deleted', false)
            ->orderBy('product.createdAt', 'DESC')
            ->addOrderBy('product.id', 'DESC')
            ->setMaxResults($limit);

        if ($fournisseur !== null) {
            $qb->andWhere('product.fournisseur = :fournisseur')
                ->setParameter('fournisseur', $fournisseur);
        }

        return $qb->getQuery()->getResult();
    }

    public function findForDatatable(int $start, int $length, string $search, ?User $fournisseur = null): array
    {
        $qb = $this->createQueryBuilder('p')
            ->leftJoin('p.fournisseur', 'u')
            ->addSelect('u')
            ->andWhere('p.isDeleted = :deleted')
            ->setParameter('deleted', false);

        if ($fournisseur !== null) {
            $qb->andWhere('p.fournisseur = :fournisseur')
                ->setParameter('fournisseur', $fournisseur);
        }

        if ($search !== '') {
            $qb->andWhere('p.libelle LIKE :s OR p.description LIKE :s OR u.nom LIKE :s OR u.prenom LIKE :s OR u.libelle LIKE :s')
                ->setParameter('s', '%' . $search . '%');
        }

        // Product ne possède pas encore de date de création : l'identifiant
        // auto-incrémenté est le meilleur indicateur disponible de récence.
        $qb->orderBy('p.id', 'DESC')
            ->setFirstResult($start)
            ->setMaxResults($length);

        $paginator = new Paginator($qb);
        $rows = [];

        foreach ($paginator as $product) {
            $rows[] = [
                'id' => $product->getId(),
                'libelle' => $product->getLibelle(),
                'description' => $product->getDescription(),
                'image' => $product->getImage(),
                'prix' => $product->getPrix(),
                'fournisseur' => $product->getFournisseur()
                    ? $product->getFournisseur()->getPrenom()
                        . ' '
                        . $product->getFournisseur()->getNom()
                        . ' ('
                        . $product->getFournisseur()->getLibelle()
                        . ')'
                    : '-',
            ];
        }

        $total = $this->createQueryBuilder('p')
            ->select('COUNT(p.id)')
            ->andWhere('p.isDeleted = :deleted')
            ->setParameter('deleted', false);

        if ($fournisseur !== null) {
            $total->andWhere('p.fournisseur = :fournisseur')
                ->setParameter('fournisseur', $fournisseur);
        }

        $total = $total->getQuery()->getSingleScalarResult();

        $filtered = ($search === '')
            ? $total
            : (clone $qb)->select('COUNT(p.id)')->setMaxResults(null)->setFirstResult(null)->getQuery()->getSingleScalarResult();

        return [
            'rows' => $rows,
            'filtered' => (int) $filtered,
            'total' => (int) $total,
        ];
    }
}

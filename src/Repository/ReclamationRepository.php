<?php

namespace App\Repository;

use App\Entity\Reclamation;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\Tools\Pagination\Paginator;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Reclamation>
 */
class ReclamationRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Reclamation::class);
    }

    public function countBySupplierAndStatuses(
        User $fournisseur,
        array $statuts
    ): int {
        return (int) $this->createQueryBuilder('reclamation')
            ->select('COUNT(reclamation.id)')
            ->andWhere('reclamation.fournisseur = :fournisseur')
            ->andWhere('reclamation.statut IN (:statuts)')
            ->setParameter('fournisseur', $fournisseur)
            ->setParameter('statuts', $statuts)
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function findForDatatable(
        int $start,
        int $length,
        string $search,
        ?User $fournisseur = null,
        ?string $statut = null,
        ?string $priorite = null,
        ?string $categorie = null,
    ): array {
        $qb = $this->createQueryBuilder('reclamation')
            ->leftJoin('reclamation.fournisseur', 'fournisseur')
            ->addSelect('fournisseur')
            ->leftJoin('reclamation.adminAssigne', 'admin')
            ->addSelect('admin');

        if ($fournisseur !== null) {
            $qb->andWhere('reclamation.fournisseur = :fournisseur')
                ->setParameter('fournisseur', $fournisseur);
        }

        if ($statut !== null) {
            $qb->andWhere('reclamation.statut = :statut')
                ->setParameter('statut', $statut);
        }

        if ($priorite !== null) {
            $qb->andWhere('reclamation.priorite = :priorite')
                ->setParameter('priorite', $priorite);
        }

        if ($categorie !== null) {
            $qb->andWhere('reclamation.categorie = :categorie')
                ->setParameter('categorie', $categorie);
        }

        if ($search !== '') {
            $searchExpression = $qb->expr()->orX(
                'reclamation.objet LIKE :search',
                'reclamation.description LIKE :search',
                'fournisseur.nom LIKE :search',
                'fournisseur.prenom LIKE :search',
                'fournisseur.libelle LIKE :search'
            );

            if (ctype_digit($search)) {
                $searchExpression->add('reclamation.id = :numero');
                $qb->setParameter('numero', (int) $search);
            }

            $qb->andWhere($searchExpression)
                ->setParameter('search', '%' . $search . '%');
        }

        $filteredQuery = clone $qb;

        $qb->orderBy('reclamation.updatedAt', 'DESC')
            ->setFirstResult($start)
            ->setMaxResults($length);

        $rows = iterator_to_array(new Paginator($qb));

        $totalQuery = $this->createQueryBuilder('reclamation')
            ->select('COUNT(reclamation.id)');

        if ($fournisseur !== null) {
            $totalQuery->andWhere('reclamation.fournisseur = :fournisseur')
                ->setParameter('fournisseur', $fournisseur);
        }

        if ($statut !== null) {
            $totalQuery->andWhere('reclamation.statut = :statut')
                ->setParameter('statut', $statut);
        }

        if ($priorite !== null) {
            $totalQuery->andWhere('reclamation.priorite = :priorite')
                ->setParameter('priorite', $priorite);
        }

        if ($categorie !== null) {
            $totalQuery->andWhere('reclamation.categorie = :categorie')
                ->setParameter('categorie', $categorie);
        }

        $total = (int) $totalQuery->getQuery()->getSingleScalarResult();
        $filtered = $search === ''
            ? $total
            : (int) $filteredQuery
                ->select('COUNT(reclamation.id)')
                ->getQuery()
                ->getSingleScalarResult();

        return [
            'rows' => $rows,
            'filtered' => $filtered,
            'total' => $total,
        ];
    }
}

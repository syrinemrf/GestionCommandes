<?php

namespace App\Repository;

use App\Entity\Commande;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\Tools\Pagination\Paginator;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Commande>
 */
class CommandeRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Commande::class);
    }

    public function findForDatatable(
        int $start,
        int $length,
        string $search,
        ?User $fournisseur = null,
        ?string $statut = null,
        ?\DateTimeImmutable $dateFrom = null,
        ?\DateTimeImmutable $dateTo = null,
    ): array {
        $qb = $this->createQueryBuilder('commande')
            ->leftJoin('commande.client', 'client')
            ->addSelect('client')
            ->leftJoin('commande.fournisseur', 'fournisseur')
            ->addSelect('fournisseur')
            ->leftJoin('commande.user', 'creator')
            ->addSelect('creator')
            ->andWhere('commande.isDeleted = :deleted')
            ->setParameter('deleted', false);

        if ($fournisseur !== null) {
            $qb->andWhere('commande.fournisseur = :fournisseur')
                ->setParameter('fournisseur', $fournisseur);
        }

        if ($statut !== null) {
            $qb->andWhere('commande.statut = :statut')
                ->setParameter('statut', $statut);
        }

        if ($dateFrom !== null) {
            $qb->andWhere('commande.date >= :dateFrom')
                ->setParameter('dateFrom', $dateFrom);
        }

        if ($dateTo !== null) {
            $qb->andWhere('commande.date < :dateTo')
                ->setParameter('dateTo', $dateTo->modify('+1 day'));
        }

        if ($search !== '') {
            $searchExpression = $qb->expr()->orX(
                'commande.statut LIKE :search',
                'client.nom LIKE :search',
                'client.prenom LIKE :search',
                'client.societe LIKE :search',
                'fournisseur.nom LIKE :search',
                'fournisseur.prenom LIKE :search',
                'fournisseur.libelle LIKE :search',
                'creator.nom LIKE :search',
                'creator.prenom LIKE :search'
            );

            if (ctype_digit($search)) {
                $searchExpression->add('commande.numero = :numero');
                $qb->setParameter('numero', (int) $search);
            }

            $qb->andWhere($searchExpression)
                ->setParameter('search', '%' . $search . '%');
        }

        $filteredQuery = clone $qb;

        $qb->orderBy('commande.id', 'DESC')
            ->setFirstResult($start)
            ->setMaxResults($length);

        $rows = iterator_to_array(new Paginator($qb));

        $totalQuery = $this->createQueryBuilder('commande')
            ->select('COUNT(commande.id)')
            ->andWhere('commande.isDeleted = :deleted')
            ->setParameter('deleted', false);

        if ($fournisseur !== null) {
            $totalQuery->andWhere('commande.fournisseur = :fournisseur')
                ->setParameter('fournisseur', $fournisseur);
        }

        if ($statut !== null) {
            $totalQuery->andWhere('commande.statut = :statut')
                ->setParameter('statut', $statut);
        }

        if ($dateFrom !== null) {
            $totalQuery->andWhere('commande.date >= :dateFrom')
                ->setParameter('dateFrom', $dateFrom);
        }

        if ($dateTo !== null) {
            $totalQuery->andWhere('commande.date < :dateTo')
                ->setParameter('dateTo', $dateTo->modify('+1 day'));
        }

        $total = (int) $totalQuery->getQuery()->getSingleScalarResult();
        $filtered = $search === ''
            ? $total
            : (int) $filteredQuery
                ->select('COUNT(commande.id)')
                ->getQuery()
                ->getSingleScalarResult();

        return [
            'rows' => $rows,
            'filtered' => $filtered,
            'total' => $total,
        ];
    }
}

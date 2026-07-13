<?php

namespace App\Repository;

use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Doctrine\ORM\Tools\Pagination\Paginator;

/**
 * @extends ServiceEntityRepository<User>
 */
class UserRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, User::class);
    }

    public function findForDatatable(int $start, int $length, string $search): array
    {
        $qb = $this->createQueryBuilder('u')
            ->andWhere('u.isDeleted = :deleted')->setParameter('deleted', false);

        if ($search !== '') {
            $qb->andWhere('u.nom LIKE :s OR u.prenom LIKE :s OR u.email LIKE :s OR u.libelle LIKE :s')
            ->setParameter('s', '%'.$search.'%');
        }

        $qb->orderBy('u.id', 'DESC')
        ->setFirstResult($start)
        ->setMaxResults($length);

        $paginator = new Paginator($qb);
        $rows = [];
        foreach ($paginator as $user) {
            $rows[] = [
                'id' => $user->getId(),
                'nom' => $user->getNom(),
                'prenom' => $user->getPrenom(),
                'email' => $user->getEmail(),
                'role' => $user->getRole(),
                'libelle' => $user->getLibelle() ?: '-',
            ];
        }

        // total (sans filter)
        $total = $this->createQueryBuilder('u')
            ->select('COUNT(u.id)')
            ->andWhere('u.isDeleted = :deleted')->setParameter('deleted', false)
            ->getQuery()->getSingleScalarResult();

        // filtered count (si search présent)
        $filtered = ($search === '') ? $total : (clone $qb)->select('COUNT(u.id)')->setMaxResults(null)->setFirstResult(null)->getQuery()->getSingleScalarResult();

        return ['rows' => $rows, 'filtered' => (int)$filtered, 'total' => (int)$total];
    }
}

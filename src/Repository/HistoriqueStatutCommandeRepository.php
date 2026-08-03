<?php

namespace App\Repository;

use App\Entity\HistoriqueStatutCommande;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<HistoriqueStatutCommande>
 */
class HistoriqueStatutCommandeRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, HistoriqueStatutCommande::class);
    }

    /**
     * @param int[] $commandeIds
     * @return array<int, \DateTimeImmutable>
     */
    public function findLatestDatesByCommandeIds(array $commandeIds): array
    {
        if ($commandeIds === []) {
            return [];
        }

        $rows = $this->createQueryBuilder('historique')
            ->select('IDENTITY(historique.commande) AS commandeId')
            ->addSelect('MAX(historique.changedAt) AS changedAt')
            ->andWhere('IDENTITY(historique.commande) IN (:commandeIds)')
            ->setParameter('commandeIds', $commandeIds)
            ->groupBy('historique.commande')
            ->getQuery()
            ->getArrayResult();

        $dates = [];

        foreach ($rows as $row) {
            $dates[(int) $row['commandeId']] = new \DateTimeImmutable(
                (string) $row['changedAt']
            );
        }

        return $dates;
    }

    public function findLatestDateForCommande(
        \App\Entity\Commande $commande,
    ): ?\DateTimeImmutable {
        $historique = $this->findOneBy(
            ['commande' => $commande],
            ['changedAt' => 'DESC']
        );

        return $historique?->getChangedAt();
    }
}

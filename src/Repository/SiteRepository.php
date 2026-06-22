<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Site;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Site>
 */
class SiteRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Site::class);
    }

    public function save(Site $site, bool $flush = true): void
    {
        $this->getEntityManager()->persist($site);
        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(Site $site, bool $flush = true): void
    {
        $this->getEntityManager()->remove($site);
        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    /**
     * @return Site[]
     */
    public function findByOwner(User $owner): array
    {
        return $this->createQueryBuilder('s')
            ->andWhere('s.owner = :owner')
            ->setParameter('owner', $owner)
            ->orderBy('s.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * @return Site[]
     */
    public function findAllForScan(): array
    {
        return $this->createQueryBuilder('s')
            ->orderBy('s.lastScannedAt', 'ASC')
            ->getQuery()
            ->getResult();
    }
}

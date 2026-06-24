<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Site;
use App\Entity\SiteAlert;
use App\Entity\User;
use App\Enum\AlertType;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<SiteAlert>
 */
class SiteAlertRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, SiteAlert::class);
    }

    public function findOneByDedup(Site $site, AlertType $type, string $dedupKey): ?SiteAlert
    {
        return $this->findOneBy(['site' => $site, 'type' => $type, 'dedupKey' => $dedupKey]);
    }

    /**
     * Open alerts for every site owned by the given user, newest first.
     *
     * @return SiteAlert[]
     */
    public function findOpenForOwner(User $owner): array
    {
        return $this->createQueryBuilder('a')
            ->innerJoin('a.site', 's')
            ->andWhere('s.owner = :owner')
            ->andWhere('a.resolved = false')
            ->setParameter('owner', $owner)
            ->orderBy('a.severity', 'DESC')
            ->addOrderBy('a.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Open alerts that have never been notified, across all owners. Used by the scan to send
     * digests; the site is eager-loaded to group by owner without extra queries.
     *
     * @return SiteAlert[]
     */
    public function findUnnotifiedOpen(): array
    {
        return $this->createQueryBuilder('a')
            ->innerJoin('a.site', 's')
            ->addSelect('s')
            ->andWhere('a.resolved = false')
            ->andWhere('a.notifiedAt IS NULL')
            ->orderBy('s.owner', 'ASC')
            ->addOrderBy('a.severity', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function countOpenForOwner(User $owner): int
    {
        return (int) $this->createQueryBuilder('a')
            ->select('COUNT(a.id)')
            ->innerJoin('a.site', 's')
            ->andWhere('s.owner = :owner')
            ->andWhere('a.resolved = false')
            ->setParameter('owner', $owner)
            ->getQuery()
            ->getSingleScalarResult();
    }
}

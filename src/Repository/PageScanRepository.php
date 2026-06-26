<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\PageScan;
use App\Entity\Site;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<PageScan>
 */
class PageScanRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, PageScan::class);
    }

    public function save(PageScan $pageScan): void
    {
        $em = $this->getEntityManager();
        $em->persist($pageScan);
        $em->flush();
    }

    /**
     * @return PageScan[] most recent first
     */
    public function findHistoryForSite(Site $site): array
    {
        return $this->createQueryBuilder('ps')
            ->andWhere('ps.site = :site')
            ->setParameter('site', $site)
            ->orderBy('ps.scannedAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function findLatestForSite(Site $site): ?PageScan
    {
        return $this->createQueryBuilder('ps')
            ->andWhere('ps.site = :site')
            ->setParameter('site', $site)
            ->orderBy('ps.scannedAt', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }
}

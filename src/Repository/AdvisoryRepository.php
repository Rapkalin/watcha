<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Advisory;
use App\Enum\Technology;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Advisory>
 */
class AdvisoryRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Advisory::class);
    }

    public function findOneBySourceAndExternalId(string $source, string $externalId): ?Advisory
    {
        return $this->findOneBy(['source' => $source, 'externalId' => $externalId]);
    }

    /**
     * @return Advisory[]
     */
    public function findByTechnology(Technology $technology): array
    {
        return $this->createQueryBuilder('a')
            ->andWhere('a.technology = :tech')
            ->setParameter('tech', $technology)
            ->orderBy('a.publishedAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * @return Advisory[]
     */
    public function findLatest(int $limit = 50): array
    {
        return $this->createQueryBuilder('a')
            ->orderBy('a.publishedAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    public function countAll(): int
    {
        return (int) $this->createQueryBuilder('a')
            ->select('COUNT(a.id)')
            ->getQuery()
            ->getSingleScalarResult();
    }
}

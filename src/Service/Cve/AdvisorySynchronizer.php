<?php

declare(strict_types=1);

namespace App\Service\Cve;

use App\Entity\Advisory;
use App\Enum\Technology;
use App\Repository\AdvisoryRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;

/**
 * Pulls advisories from every registered provider and upserts them into the database.
 */
final class AdvisorySynchronizer
{
    /**
     * @param iterable<AdvisoryProviderInterface> $providers
     */
    public function __construct(
        private readonly iterable $providers,
        private readonly AdvisoryRepository $advisoryRepository,
        private readonly EntityManagerInterface $em,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function synchronize(?Technology $only = null): SyncReport
    {
        $report = new SyncReport();
        $technologies = null !== $only ? [$only] : Technology::all();

        foreach ($technologies as $technology) {
            foreach ($this->providers as $provider) {
                if (!$provider->supports($technology)) {
                    continue;
                }

                try {
                    foreach ($provider->fetch($technology) as $dto) {
                        $this->upsert($dto, $report);
                    }
                } catch (AdvisoryFetchException $e) {
                    $report->addError($technology, $e->getMessage());
                    $this->logger->error('Advisory fetch failed', [
                        'technology' => $technology->value,
                        'provider' => $provider::class,
                        'error' => $e->getMessage(),
                    ]);
                }
            }
        }

        $this->em->flush();

        return $report;
    }

    private function upsert(AdvisoryDto $dto, SyncReport $report): void
    {
        $advisory = $this->advisoryRepository->findOneBySourceAndExternalId($dto->source, $dto->externalId);

        if (null === $advisory) {
            $advisory = new Advisory($dto->technology, $dto->source, $dto->externalId);
            $this->em->persist($advisory);
            ++$report->created;
        } else {
            ++$report->updated;
            $advisory->touchImported();
        }

        $advisory
            ->setTitle($dto->title)
            ->setSummary($dto->summary)
            ->setSeverity($dto->severity)
            ->setCveId($dto->cveId)
            ->setAffectedConstraint($dto->affectedConstraint)
            ->setFixedVersion($dto->fixedVersion)
            ->setReferenceUrl($dto->referenceUrl)
            ->setPublishedAt($dto->publishedAt);
    }
}

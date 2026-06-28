<?php

declare(strict_types=1);

namespace App\Tests\Service\Cve;

use App\Entity\Advisory;
use App\Enum\Technology;
use App\Repository\AdvisoryRepository;
use App\Service\Cve\AdvisoryDto;
use App\Service\Cve\AdvisoryProviderInterface;
use App\Service\Cve\AdvisorySynchronizer;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

final class AdvisorySynchronizerTest extends TestCase
{
    /**
     * Regression: OSV can yield the same advisory id more than once in a single run. Nothing is
     * flushed until the end, so without in-run de-duplication the second occurrence would be
     * persisted as a second row and abort the whole sync on the unique (source, external_id) index.
     */
    public function testSameAdvisoryYieldedTwiceIsPersistedOnce(): void
    {
        $provider = $this->provider([Technology::SYMFONY], [
            $this->dto('GHSA-A'),
            $this->dto('GHSA-A'), // duplicate within the same run
            $this->dto('GHSA-B'),
        ]);

        $persisted = [];
        $sync = new AdvisorySynchronizer(
            [$provider],
            $this->repositoryReturningNoExisting(),
            $this->entityManagerCollecting($persisted),
            new NullLogger(),
        );

        $report = $sync->synchronize(Technology::SYMFONY);

        self::assertCount(2, $persisted, 'GHSA-A must be persisted once, GHSA-B once.');
        self::assertSame(2, $report->created);
    }

    /**
     * The unique key is (source, external_id) with no technology, so the same id surfacing under two
     * technologies (e.g. a Symfony component bundled in Drupal) must be persisted only once.
     */
    public function testSameAdvisoryAcrossTechnologiesIsPersistedOnce(): void
    {
        $provider = $this->provider(
            [Technology::SYMFONY, Technology::DRUPAL],
            [$this->dto('GHSA-shared')],
        );

        $persisted = [];
        $sync = new AdvisorySynchronizer(
            [$provider],
            $this->repositoryReturningNoExisting(),
            $this->entityManagerCollecting($persisted),
            new NullLogger(),
        );

        $report = $sync->synchronize(); // all technologies

        self::assertCount(1, $persisted);
        self::assertSame(1, $report->created);
    }

    private function dto(string $externalId): AdvisoryDto
    {
        return new AdvisoryDto(
            technology: Technology::SYMFONY,
            source: 'osv.dev',
            externalId: $externalId,
            title: 'Advisory '.$externalId,
        );
    }

    /**
     * @param Technology[]  $supported
     * @param AdvisoryDto[] $dtos
     */
    private function provider(array $supported, array $dtos): AdvisoryProviderInterface
    {
        return new class($supported, $dtos) implements AdvisoryProviderInterface {
            /**
             * @param Technology[]  $supported
             * @param AdvisoryDto[] $dtos
             */
            public function __construct(private readonly array $supported, private readonly array $dtos)
            {
            }

            public function supports(Technology $technology): bool
            {
                return in_array($technology, $this->supported, true);
            }

            public function fetch(Technology $technology): iterable
            {
                yield from $this->dtos;
            }
        };
    }

    private function repositoryReturningNoExisting(): AdvisoryRepository
    {
        $repo = $this->createMock(AdvisoryRepository::class);
        $repo->method('findOneBySourceAndExternalId')->willReturn(null);

        return $repo;
    }

    /**
     * @param list<object> $persisted captured by reference
     */
    private function entityManagerCollecting(array &$persisted): EntityManagerInterface
    {
        $em = $this->createMock(EntityManagerInterface::class);
        $em->method('persist')->willReturnCallback(static function (object $entity) use (&$persisted): void {
            if ($entity instanceof Advisory) {
                $persisted[] = $entity;
            }
        });

        return $em;
    }
}

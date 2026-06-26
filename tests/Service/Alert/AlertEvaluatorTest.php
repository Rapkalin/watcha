<?php

declare(strict_types=1);

namespace App\Tests\Service\Alert;

use App\Entity\Advisory;
use App\Entity\Site;
use App\Entity\SiteAlert;
use App\Enum\AlertType;
use App\Enum\Severity;
use App\Enum\Technology;
use App\Repository\AdvisoryRepository;
use App\Repository\SiteAlertRepository;
use App\Service\Alert\AlertEvaluator;
use App\Service\Version\LatestVersionResolverInterface;
use App\Service\Version\VersionComparator;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

final class AlertEvaluatorTest extends TestCase
{
    public function testCreatesCveAlertForAffectedVersion(): void
    {
        $site = (new Site())->setName('Demo')->setUrl('https://demo.test')
            ->setTechnology(Technology::LARAVEL)
            ->setDetectedVersion('11.20.0');

        $advisory = (new Advisory(Technology::LARAVEL, 'osv.dev', 'GHSA-xxxx'))
            ->setTitle('Reflected XSS')
            ->setCveId('CVE-2024-0001')
            ->setSeverity(Severity::HIGH)
            ->setAffectedConstraint('>=11.9.0,<11.36.0');

        $advisoryRepo = $this->createMock(AdvisoryRepository::class);
        $advisoryRepo->method('findByTechnology')->willReturn([$advisory]);

        $alertRepo = $this->createMock(SiteAlertRepository::class);
        $alertRepo->method('findBy')->willReturn([]);
        $alertRepo->method('findOneByDedup')->willReturn(null);

        $persisted = [];
        $em = $this->createMock(EntityManagerInterface::class);
        $em->method('persist')->willReturnCallback(static function (object $entity) use (&$persisted): void {
            $persisted[] = $entity;
        });

        $evaluator = new AlertEvaluator(
            $advisoryRepo,
            $alertRepo,
            new VersionComparator(new NullLogger()),
            $this->stubResolver(null),
            $em,
        );

        $report = $evaluator->evaluate($site, flush: false);

        self::assertSame(1, $report->created);
        self::assertCount(1, $persisted);
        self::assertInstanceOf(SiteAlert::class, $persisted[0]);
        self::assertSame(AlertType::CVE, $persisted[0]->getType());
        self::assertSame(Severity::HIGH, $persisted[0]->getSeverity());
        self::assertStringContainsString('CVE-2024-0001', $persisted[0]->getMessage());
    }

    public function testManualVersionTakesPrecedenceForCveMatching(): void
    {
        // Symfony exposes no version: detectedVersion is null, but the owner pinned one manually.
        $site = (new Site())->setName('Demo')->setUrl('https://demo.test')
            ->setManualTechnology(Technology::SYMFONY)
            ->setManualVersion('6.4.10');
        // No auto-detected version at all.
        self::assertNull($site->getDetectedVersion());
        self::assertSame('6.4.10', $site->getEffectiveVersion());

        $advisory = (new Advisory(Technology::SYMFONY, 'osv.dev', 'GHSA-sf'))
            ->setTitle('Symfony issue')
            ->setSeverity(Severity::MEDIUM)
            ->setAffectedConstraint('>=6.3.0,<6.4.40');

        $advisoryRepo = $this->createMock(AdvisoryRepository::class);
        $advisoryRepo->method('findByTechnology')->willReturn([$advisory]);
        $alertRepo = $this->createMock(SiteAlertRepository::class);
        $alertRepo->method('findBy')->willReturn([]);
        $alertRepo->method('findOneByDedup')->willReturn(null);

        $persisted = [];
        $em = $this->createMock(EntityManagerInterface::class);
        $em->method('persist')->willReturnCallback(static function (object $e) use (&$persisted): void {
            $persisted[] = $e;
        });

        $evaluator = new AlertEvaluator(
            $advisoryRepo,
            $alertRepo,
            new VersionComparator(new NullLogger()),
            $this->stubResolver(null),
            $em,
        );

        $report = $evaluator->evaluate($site, flush: false);

        self::assertSame(1, $report->created);
        self::assertSame(AlertType::CVE, $persisted[0]->getType());
    }

    public function testInvalidManualVersionIsFlaggedAndSkipsEvaluation(): void
    {
        $site = (new Site())->setName('Demo')->setUrl('https://demo.test')
            ->setManualTechnology(Technology::SYMFONY)
            ->setManualVersion('6.4.999'); // does not exist

        $advisory = (new Advisory(Technology::SYMFONY, 'osv.dev', 'GHSA-sf'))
            ->setTitle('Symfony issue')
            ->setSeverity(Severity::MEDIUM)
            ->setAffectedConstraint('>=6.3.0,<6.4.40');

        $advisoryRepo = $this->createMock(AdvisoryRepository::class);
        $advisoryRepo->expects(self::never())->method('findByTechnology');
        $alertRepo = $this->createMock(SiteAlertRepository::class);

        $persisted = [];
        $em = $this->createMock(EntityManagerInterface::class);
        $em->method('persist')->willReturnCallback(static function (object $e) use (&$persisted): void {
            $persisted[] = $e;
        });

        $evaluator = new AlertEvaluator(
            $advisoryRepo,
            $alertRepo,
            new VersionComparator(new NullLogger()),
            $this->stubResolver(null, versionExists: false),
            $em,
        );

        $report = $evaluator->evaluate($site, flush: false);

        self::assertTrue($report->manualVersionInvalid);
        self::assertSame(0, $report->created);
        self::assertCount(0, $persisted);
    }

    public function testCreatesUpdateAlertWhenOutdatedAndNoCve(): void
    {
        $site = (new Site())->setName('Demo')->setUrl('https://demo.test')
            ->setTechnology(Technology::DRUPAL)
            ->setDetectedVersion('10.2.0');

        $advisoryRepo = $this->createMock(AdvisoryRepository::class);
        $advisoryRepo->method('findByTechnology')->willReturn([]);

        $alertRepo = $this->createMock(SiteAlertRepository::class);
        $alertRepo->method('findBy')->willReturn([]);
        $alertRepo->method('findOneByDedup')->willReturn(null);

        $persisted = [];
        $em = $this->createMock(EntityManagerInterface::class);
        $em->method('persist')->willReturnCallback(static function (object $entity) use (&$persisted): void {
            $persisted[] = $entity;
        });

        $evaluator = new AlertEvaluator(
            $advisoryRepo,
            $alertRepo,
            new VersionComparator(new NullLogger()),
            $this->stubResolver('10.3.6'),
            $em,
        );

        $report = $evaluator->evaluate($site, flush: false);

        self::assertSame('10.3.6', $site->getLatestKnownVersion());
        self::assertSame(1, $report->created);
        self::assertSame(AlertType::UPDATE_AVAILABLE, $persisted[0]->getType());
    }

    private function stubResolver(?string $latest, ?bool $versionExists = true): LatestVersionResolverInterface
    {
        return new class($latest, $versionExists) implements LatestVersionResolverInterface {
            public function __construct(private readonly ?string $latest, private readonly ?bool $versionExists)
            {
            }

            public function latestStable(Technology $technology): ?string
            {
                return $this->latest;
            }

            public function versionExists(Technology $technology, string $version): ?bool
            {
                return $this->versionExists;
            }
        };
    }
}

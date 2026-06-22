<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Site;
use App\Service\Alert\AlertEvaluator;
use App\Service\Detection\SiteScanner;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Orchestrates a full refresh of a site: scan its URL, persist the detection result, then
 * (re)evaluate its alerts. This is the single entry point used by both the controller and the CLI.
 */
final class SiteMonitor
{
    public function __construct(
        private readonly SiteScanner $scanner,
        private readonly AlertEvaluator $alertEvaluator,
        private readonly EntityManagerInterface $em,
    ) {
    }

    public function refresh(Site $site): MonitorResult
    {
        $outcome = $this->scanner->scan($site->getUrl());

        // Always refresh the auto-detected technology/version. Manual overrides live in separate
        // fields, so a scan never destroys them; both values stay available for display.
        if (null !== $outcome->detection) {
            $site->setTechnology($outcome->detection->technology);
            // Keep a previously detected version if this scan could not read one.
            if (null !== $outcome->detection->version) {
                $site->setDetectedVersion($outcome->detection->version);
            }
        }

        $site->setLastScannedAt(new DateTimeImmutable());
        $site->setLastScanMessage($outcome->message);

        $report = $this->alertEvaluator->evaluate($site, flush: false);

        $this->em->flush();

        return new MonitorResult($outcome, $report);
    }
}

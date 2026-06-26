<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Site;
use App\Service\Alert\AlertEvaluator;
use App\Service\Alert\AlertReport;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Refreshes a site's security status: looks up known CVEs and the latest stable release for the
 * technology/version the owner entered by hand, then (re)evaluates its alerts. This is the single
 * entry point used by both the controller and the CLI. It no longer fetches the site itself —
 * page availability is handled separately by {@see Page\SitemapScanner}.
 */
final class SiteMonitor
{
    public function __construct(
        private readonly AlertEvaluator $alertEvaluator,
        private readonly EntityManagerInterface $em,
    ) {
    }

    public function refresh(Site $site): AlertReport
    {
        $report = $this->alertEvaluator->evaluate($site, flush: false);

        $site->setLastScannedAt(new DateTimeImmutable());
        $site->setLastScanMessage($this->summarize($site, $report));

        $this->em->flush();

        return $report;
    }

    private function summarize(Site $site, AlertReport $report): string
    {
        if ($report->manualVersionInvalid) {
            return sprintf(
                "La version « %s » n'existe pas pour %s.",
                $site->getManualVersion() ?? '',
                $site->getEffectiveTechnology()?->label() ?? 'cette technologie',
            );
        }

        if (null === $site->getEffectiveTechnology() || null === $site->getEffectiveVersion()) {
            return 'Renseignez la technologie et la version pour lancer un scan.';
        }

        $open = $site->getAlerts()->filter(static fn ($a) => !$a->isResolved())->count();

        return sprintf(
            '%s %s analysé : %d alerte(s) ouverte(s). Dernière version stable : %s.',
            $site->getEffectiveTechnology()->label(),
            $site->getEffectiveVersion(),
            $open,
            $site->getLatestKnownVersion() ?? 'inconnue',
        );
    }
}

<?php

declare(strict_types=1);

namespace App\Service\Alert;

use App\Entity\Advisory;
use App\Entity\Site;
use App\Entity\SiteAlert;
use App\Enum\AlertType;
use App\Enum\Severity;
use App\Repository\AdvisoryRepository;
use App\Repository\SiteAlertRepository;
use App\Service\Version\LatestVersionResolverInterface;
use App\Service\Version\VersionComparator;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Compares a scanned site against known advisories and the latest release, then opens/resolves
 * the corresponding {@see SiteAlert}s. Idempotent: running it repeatedly converges to the same set.
 */
final class AlertEvaluator
{
    public function __construct(
        private readonly AdvisoryRepository $advisoryRepository,
        private readonly SiteAlertRepository $alertRepository,
        private readonly VersionComparator $versions,
        private readonly LatestVersionResolverInterface $latestVersionResolver,
        private readonly EntityManagerInterface $em,
    ) {
    }

    public function evaluate(Site $site, bool $flush = true): AlertReport
    {
        $report = new AlertReport();

        $technology = $site->getEffectiveTechnology();
        if (null === $technology) {
            if ($flush) {
                $this->em->flush();
            }

            return $report;
        }

        // The latest stable release depends only on the technology, so record it as soon as the
        // technology is known — even if the version is still missing or turns out to be invalid.
        $latest = $this->latestVersionResolver->latestStable($technology);
        $site->setLatestKnownVersion($latest);

        // A manually pinned version is matched verbatim against advisories, so a typo would silently
        // produce wrong (or no) alerts. Validate it against the published releases first and remember
        // the outcome (for the UI badge): if it does not exist, flag it and skip CVE matching rather
        // than match against a version that isn't real.
        $manualVersion = $site->getManualVersion();
        $versionExists = null === $manualVersion ? null : $this->latestVersionResolver->versionExists($technology, $manualVersion);
        $site->setManualVersionExists($versionExists);

        if (false === $versionExists) {
            $report->manualVersionInvalid = true;
            if ($flush) {
                $this->em->flush();
            }

            return $report;
        }

        $this->evaluateUpdate($site, $latest, $report);
        $this->evaluateCves($site, $report);

        if ($flush) {
            $this->em->flush();
        }

        return $report;
    }

    private function evaluateUpdate(Site $site, ?string $latest, AlertReport $report): void
    {
        $current = $site->getEffectiveVersion();
        $outdated = null !== $current && null !== $latest && $this->versions->isOutdated($current, $latest);

        // Resolve stale update alerts (different target version or now up to date).
        foreach ($this->openAlerts($site, AlertType::UPDATE_AVAILABLE) as $alert) {
            if (!$outdated || $alert->getDedupKey() !== (string) $latest) {
                $alert->resolve();
                ++$report->resolved;
            }
        }

        if ($outdated) {
            $this->openOrTouch(
                $site,
                AlertType::UPDATE_AVAILABLE,
                (string) $latest,
                Severity::LOW,
                sprintf('Mise à jour disponible : %s → %s', $current, $latest),
                null,
                $report,
            );
        }
    }

    private function evaluateCves(Site $site, AlertReport $report): void
    {
        $technology = $site->getEffectiveTechnology();
        if (null === $technology) {
            return;
        }

        $current = $site->getEffectiveVersion();
        $advisories = $this->advisoryRepository->findByTechnology($technology);

        /** @var array<string, Advisory> $matching keyed by externalId */
        $matching = [];
        if (null !== $current) {
            foreach ($advisories as $advisory) {
                if ($this->versions->isAffected($current, $advisory->getAffectedConstraint())) {
                    $matching[$advisory->getExternalId()] = $advisory;
                }
            }
        }

        // Resolve CVE alerts that no longer apply (e.g. the site was upgraded out of range).
        foreach ($this->openAlerts($site, AlertType::CVE) as $alert) {
            if (!isset($matching[$alert->getDedupKey()])) {
                $alert->resolve();
                ++$report->resolved;
            }
        }

        foreach ($matching as $advisory) {
            $label = $advisory->getCveId() ?? $advisory->getExternalId();
            $this->openOrTouch(
                $site,
                AlertType::CVE,
                $advisory->getExternalId(),
                $advisory->getSeverity(),
                sprintf('%s — %s', $label, $advisory->getTitle()),
                $advisory,
                $report,
            );
        }
    }

    /**
     * @return SiteAlert[]
     */
    private function openAlerts(Site $site, AlertType $type): array
    {
        return $this->alertRepository->findBy([
            'site' => $site,
            'type' => $type,
            'resolved' => false,
        ]);
    }

    private function openOrTouch(
        Site $site,
        AlertType $type,
        string $dedupKey,
        Severity $severity,
        string $message,
        ?Advisory $advisory,
        AlertReport $report,
    ): void {
        $alert = $this->alertRepository->findOneByDedup($site, $type, $dedupKey);

        if (null === $alert) {
            $alert = new SiteAlert($site, $type, $dedupKey);
            $this->em->persist($alert);
            ++$report->created;
        } elseif ($alert->isResolved()) {
            // The condition came back (e.g. a regression) — reopen the existing alert.
            $alert->reopen();
            ++$report->reopened;
        }

        $alert->setSeverity($severity)->setMessage($message)->setAdvisory($advisory);
    }
}

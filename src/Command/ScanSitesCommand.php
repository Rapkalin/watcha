<?php

declare(strict_types=1);

namespace App\Command;

use App\Repository\SiteRepository;
use App\Service\Alert\AlertNotifier;
use App\Service\SiteMonitor;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:sites:scan',
    description: 'Re-evaluates CVE alerts and the latest stable version for every monitored site.',
)]
final class ScanSitesCommand extends Command
{
    public function __construct(
        private readonly SiteRepository $siteRepository,
        private readonly SiteMonitor $monitor,
        private readonly AlertNotifier $alertNotifier,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $sites = $this->siteRepository->findAllForScan();

        if ([] === $sites) {
            $io->info('Aucun site à scanner.');

            return Command::SUCCESS;
        }

        $io->title(sprintf('Scan de %d site(s)', count($sites)));
        $rows = [];
        $createdAlerts = 0;

        foreach ($sites as $site) {
            $report = $this->monitor->refresh($site);
            $createdAlerts += $report->created + $report->reopened;
            $rows[] = [
                $site->getName(),
                $site->getEffectiveTechnology()?->label() ?? '—',
                $site->getEffectiveVersion() ?? '—',
                $site->getLatestKnownVersion() ?? '—',
                sprintf('+%d / -%d', $report->created + $report->reopened, $report->resolved),
            ];
        }

        $io->table(['Site', 'Techno', 'Version', 'Dernière', 'Alertes (ouvertes/résolues)'], $rows);
        $io->success(sprintf('Scan terminé. %d nouvelle(s) alerte(s).', $createdAlerts));

        // Notify owners about their new alerts (one digest per owner).
        $notified = $this->alertNotifier->notifyPending();
        if ($notified > 0) {
            $io->info(sprintf('%d alerte(s) notifiée(s) par e-mail.', $notified));
        }

        return Command::SUCCESS;
    }
}

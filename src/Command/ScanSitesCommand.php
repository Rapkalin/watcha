<?php

declare(strict_types=1);

namespace App\Command;

use App\Repository\SiteRepository;
use App\Service\SiteMonitor;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:sites:scan',
    description: 'Scans every monitored site, refreshes detected versions and (re)evaluates alerts.',
)]
final class ScanSitesCommand extends Command
{
    public function __construct(
        private readonly SiteRepository $siteRepository,
        private readonly SiteMonitor $monitor,
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
            $result = $this->monitor->refresh($site);
            $createdAlerts += $result->alerts->created + $result->alerts->reopened;
            $rows[] = [
                $site->getName(),
                $site->getTechnology()?->label() ?? '—',
                $site->getDetectedVersion() ?? '—',
                $site->getLatestKnownVersion() ?? '—',
                sprintf('+%d / -%d', $result->alerts->created + $result->alerts->reopened, $result->alerts->resolved),
            ];
        }

        $io->table(['Site', 'Techno', 'Version', 'Dernière', 'Alertes (ouvertes/résolues)'], $rows);
        $io->success(sprintf('Scan terminé. %d nouvelle(s) alerte(s).', $createdAlerts));

        return Command::SUCCESS;
    }
}

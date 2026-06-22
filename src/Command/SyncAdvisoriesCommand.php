<?php

declare(strict_types=1);

namespace App\Command;

use App\Enum\Technology;
use App\Service\Cve\AdvisorySynchronizer;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:cve:sync',
    description: 'Fetches and stores security advisories for the monitored technologies.',
)]
final class SyncAdvisoriesCommand extends Command
{
    public function __construct(private readonly AdvisorySynchronizer $synchronizer)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('technology', 't', InputOption::VALUE_REQUIRED, 'Limit the sync to one technology (symfony, laravel, drupal, wordpress).');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $only = null;
        if (null !== $value = $input->getOption('technology')) {
            $only = Technology::tryFrom((string) $value);
            if (null === $only) {
                $io->error(sprintf('Unknown technology "%s".', $value));

                return Command::INVALID;
            }
        }

        $io->title('Synchronisation des advisories');
        $report = $this->synchronizer->synchronize($only);

        $io->success(sprintf('%d advisories traitées (%d nouvelles, %d mises à jour).', $report->total(), $report->created, $report->updated));

        if ($report->hasErrors()) {
            foreach ($report->errors as $error) {
                $io->warning(sprintf('[%s] %s', $error['technology'], $error['message']));
            }

            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }
}

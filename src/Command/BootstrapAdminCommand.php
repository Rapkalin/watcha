<?php

declare(strict_types=1);

namespace App\Command;

use App\Entity\User;
use App\Repository\UserRepository;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/**
 * Idempotent deploy-time bootstrap: ensures at least one approved admin exists.
 *
 * Meant to be called from the deploy script (infra/deploy/release.sh) right after the
 * migrations. It is a no-op once any approved admin is present, so it is safe to run on
 * every deploy. Credentials come from the ADMIN_EMAIL / ADMIN_PASSWORD env vars (defined
 * in the server's shared/.env), or from the --email / --password options for local use.
 */
#[AsCommand(
    name: 'app:admin:bootstrap',
    description: 'Creates the default admin account if no approved admin exists yet (idempotent).',
)]
final class BootstrapAdminCommand extends Command
{
    public function __construct(
        private readonly UserRepository $userRepository,
        private readonly UserPasswordHasherInterface $passwordHasher,
        // `default::` so an unset var resolves to null instead of throwing — the prod .env
        // is the server's shared/.env, which may not define these. Stays a safe no-op below.
        #[Autowire('%env(default::ADMIN_EMAIL)%')]
        private readonly ?string $adminEmail,
        #[Autowire('%env(default::ADMIN_PASSWORD)%')]
        private readonly ?string $adminPassword,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('email', null, InputOption::VALUE_REQUIRED, 'Admin e-mail (defaults to the ADMIN_EMAIL env var)')
            ->addOption('password', null, InputOption::VALUE_REQUIRED, 'Admin password (defaults to the ADMIN_PASSWORD env var)');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        // Idempotent: nothing to do once an approved admin is present.
        if ($this->userRepository->countApprovedAdmins() > 0) {
            $io->info('An approved admin already exists — nothing to do.');

            return Command::SUCCESS;
        }

        $email = (string) ($input->getOption('email') ?? $this->adminEmail ?? '');
        $password = (string) ($input->getOption('password') ?? $this->adminPassword ?? '');

        // Don't break the deploy if the credentials weren't provided: warn loudly and move on.
        if ('' === $email || '' === $password) {
            $io->warning('No approved admin and ADMIN_EMAIL / ADMIN_PASSWORD are unset: no account created. Set them in shared/.env then redeploy (or use app:user:create).');

            return Command::SUCCESS;
        }

        // An account may exist for that e-mail without being an approved admin (e.g. a pending
        // self-registration). Don't silently clobber it — surface the conflict instead.
        if (null !== $this->userRepository->findOneBy(['email' => $email])) {
            $io->error(sprintf('An account already exists for "%s" but is not an approved admin. Approve/promote it manually.', $email));

            return Command::FAILURE;
        }

        $user = new User();
        $user->setEmail($email)
            ->setRoles([User::ROLE_ADMIN])
            ->setApproved(true)
            ->setEmailVerified(true)
            ->setPassword($this->passwordHasher->hashPassword($user, $password));

        $this->userRepository->save($user);

        $io->success(sprintf('Default admin "%s" created and approved.', $email));

        return Command::SUCCESS;
    }
}

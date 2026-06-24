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
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/**
 * Bootstrap command to create the first (approved) admin account, since registration only ever
 * creates pending basic users.
 */
#[AsCommand(
    name: 'app:user:create',
    description: 'Creates an approved user (defaults to an admin) from the CLI.',
)]
final class CreateUserCommand extends Command
{
    public function __construct(
        private readonly UserRepository $userRepository,
        private readonly UserPasswordHasherInterface $passwordHasher,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('email', null, InputOption::VALUE_REQUIRED, 'Account e-mail')
            ->addOption('password', null, InputOption::VALUE_REQUIRED, 'Account password')
            ->addOption('role', null, InputOption::VALUE_REQUIRED, 'admin | maintainer | basic', 'admin');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $email = (string) ($input->getOption('email') ?? $io->ask('E-mail'));
        $password = (string) ($input->getOption('password') ?? $io->askHidden('Mot de passe'));
        $role = strtolower((string) $input->getOption('role'));

        if ('' === $email || '' === $password) {
            $io->error('E-mail et mot de passe sont requis.');

            return Command::INVALID;
        }

        if (null !== $this->userRepository->findOneBy(['email' => $email])) {
            $io->error(sprintf('Un compte existe déjà pour "%s".', $email));

            return Command::FAILURE;
        }

        $roles = match ($role) {
            'admin' => [User::ROLE_ADMIN],
            'maintainer' => [User::ROLE_MAINTAINER],
            'basic' => [],
            default => null,
        };
        if (null === $roles) {
            $io->error('Role invalide (admin, maintainer ou basic).');

            return Command::INVALID;
        }

        $user = new User();
        $user->setEmail($email)
            ->setRoles($roles)
            ->setApproved(true)
            ->setEmailVerified(true)
            ->setPassword($this->passwordHasher->hashPassword($user, $password));

        $this->userRepository->save($user);

        $io->success(sprintf('Compte "%s" créé (%s) et approuvé.', $email, $role));

        return Command::SUCCESS;
    }
}

<?php

declare(strict_types=1);

namespace App\Security;

use App\Entity\User;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAccountStatusException;
use Symfony\Component\Security\Core\User\UserCheckerInterface;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * Prevents accounts that have not been approved by a maintainer/admin from authenticating.
 * E-mail verification is informational only and does not gate login — an admin can approve an
 * account whose e-mail is not yet verified, and approval grants access.
 */
final class AppUserChecker implements UserCheckerInterface
{
    public function checkPreAuth(UserInterface $user): void
    {
        if (!$user instanceof User) {
            return;
        }

        if (!$user->isApproved()) {
            throw new CustomUserMessageAccountStatusException("Votre compte est en attente de validation par un administrateur. Vous recevrez l'accès une fois approuvé.");
        }
    }

    public function checkPostAuth(UserInterface $user): void
    {
        // No post-authentication checks (e.g. credentials expiry) for now.
    }
}

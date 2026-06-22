<?php

declare(strict_types=1);

namespace App\Security\Voter;

use App\Entity\Site;
use App\Entity\User;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

/**
 * @extends Voter<string, Site>
 */
final class SiteVoter extends Voter
{
    public const VIEW = 'SITE_VIEW';
    public const EDIT = 'SITE_EDIT';
    public const DELETE = 'SITE_DELETE';

    protected function supports(string $attribute, mixed $subject): bool
    {
        return in_array($attribute, [self::VIEW, self::EDIT, self::DELETE], true)
            && $subject instanceof Site;
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token): bool
    {
        $user = $token->getUser();
        if (!$user instanceof User) {
            return false;
        }

        // $subject is a Site here (guaranteed by supports()).
        // Admins can manage any site; everyone else only their own.
        if (in_array(User::ROLE_ADMIN, $user->getRoles(), true)) {
            return true;
        }

        return $subject->getOwner() === $user;
    }
}

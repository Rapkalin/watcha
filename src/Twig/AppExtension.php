<?php

declare(strict_types=1);

namespace App\Twig;

use App\Entity\User;
use App\Enum\Severity;
use App\Repository\SiteAlertRepository;
use App\Repository\UserRepository;
use Symfony\Bundle\SecurityBundle\Security;
use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;
use Twig\TwigFunction;

final class AppExtension extends AbstractExtension
{
    public function __construct(
        private readonly Security $security,
        private readonly SiteAlertRepository $alertRepository,
        private readonly UserRepository $userRepository,
    ) {
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('open_alert_count', $this->openAlertCount(...)),
            new TwigFunction('pending_user_count', $this->pendingUserCount(...)),
        ];
    }

    public function getFilters(): array
    {
        return [
            new TwigFilter('severity_class', $this->severityClass(...)),
        ];
    }

    public function openAlertCount(): int
    {
        $user = $this->security->getUser();

        return $user instanceof User ? $this->alertRepository->countOpenForOwner($user) : 0;
    }

    public function pendingUserCount(): int
    {
        if (!$this->security->isGranted(User::ROLE_MAINTAINER)) {
            return 0;
        }

        return count($this->userRepository->findPendingApproval());
    }

    public function severityClass(Severity $severity): string
    {
        return $severity->value;
    }
}

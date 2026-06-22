<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\User;
use App\Repository\AdvisoryRepository;
use App\Repository\SiteAlertRepository;
use App\Repository\SiteRepository;
use App\Repository\UserRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted(User::ROLE_USER)]
final class DashboardController extends AbstractController
{
    #[Route('/', name: 'app_dashboard')]
    public function index(
        SiteRepository $siteRepository,
        SiteAlertRepository $alertRepository,
        AdvisoryRepository $advisoryRepository,
        UserRepository $userRepository,
    ): Response {
        /** @var User $user */
        $user = $this->getUser();

        $sites = $siteRepository->findByOwner($user);
        $alerts = $alertRepository->findOpenForOwner($user);

        return $this->render('dashboard/index.html.twig', [
            'sites' => $sites,
            'alerts' => $alerts,
            'advisory_count' => $advisoryRepository->countAll(),
            'pending_users' => $this->isGranted(User::ROLE_MAINTAINER)
                ? count($userRepository->findPendingApproval())
                : null,
        ]);
    }
}

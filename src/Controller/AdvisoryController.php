<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\User;
use App\Enum\Technology;
use App\Repository\AdvisoryRepository;
use App\Service\Cve\AdvisorySynchronizer;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/advisories')]
#[IsGranted(User::ROLE_USER)]
final class AdvisoryController extends AbstractController
{
    #[Route('', name: 'app_advisory_index', methods: ['GET'])]
    public function index(Request $request, AdvisoryRepository $advisoryRepository): Response
    {
        $technology = Technology::tryFrom((string) $request->query->get('technology', ''));

        $advisories = null !== $technology
            ? $advisoryRepository->findByTechnology($technology)
            : $advisoryRepository->findLatest(100);

        return $this->render('advisory/index.html.twig', [
            'advisories' => $advisories,
            'technologies' => Technology::all(),
            'current_technology' => $technology,
        ]);
    }

    /**
     * Fetches advisories from the providers, for one technology or all of them.
     * Restricted to maintainers/admins because it triggers outbound network calls.
     */
    #[Route('/sync', name: 'app_advisory_sync', methods: ['POST'])]
    #[IsGranted(User::ROLE_MAINTAINER)]
    public function sync(Request $request, AdvisorySynchronizer $synchronizer): Response
    {
        if (!$this->isCsrfTokenValid('advisory_sync', (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Jeton CSRF invalide.');
        }

        $technologyValue = (string) $request->request->get('technology', '');
        $technology = Technology::tryFrom($technologyValue);

        $report = $synchronizer->synchronize($technology);

        $scope = null !== $technology ? $technology->label() : 'toutes les technologies';
        if ($report->hasErrors()) {
            $this->addFlash('error', sprintf(
                'Synchronisation %s : %d advisories traitées, mais %d erreur(s) — %s',
                $scope,
                $report->total(),
                \count($report->errors),
                $report->errors[0]['message'] ?? '',
            ));
        } else {
            $this->addFlash('success', sprintf(
                'Synchronisation %s terminée : %d advisories (%d nouvelles, %d mises à jour).',
                $scope,
                $report->total(),
                $report->created,
                $report->updated,
            ));
        }

        return $this->redirectToRoute('app_advisory_index', array_filter(['technology' => $technologyValue]));
    }
}

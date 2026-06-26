<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\PageResult;
use App\Entity\PageScan;
use App\Entity\Site;
use App\Entity\User;
use App\Form\SiteType;
use App\Form\SiteVersionType;
use App\Repository\PageScanRepository;
use App\Repository\SiteRepository;
use App\Security\Voter\SiteVoter;
use App\Service\Alert\AlertEvaluator;
use App\Service\Page\SitemapScanner;
use App\Service\SiteMonitor;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/sites')]
#[IsGranted(User::ROLE_USER)]
final class SiteController extends AbstractController
{
    #[Route('', name: 'app_site_index', methods: ['GET'])]
    public function index(SiteRepository $siteRepository): Response
    {
        /** @var User $user */
        $user = $this->getUser();

        return $this->render('site/index.html.twig', [
            'sites' => $siteRepository->findByOwner($user),
        ]);
    }

    #[Route('/new', name: 'app_site_new', methods: ['GET', 'POST'])]
    public function new(Request $request, SiteRepository $siteRepository): Response
    {
        /** @var User $user */
        $user = $this->getUser();

        $site = new Site();
        $site->setOwner($user);
        $form = $this->createForm(SiteType::class, $site);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $siteRepository->save($site);
            $this->addFlash('success', 'Site ajouté. Renseignez la technologie et la version pour activer le scan.');

            // Send the owner straight to the form where they enter the technology and version.
            return $this->redirectToRoute('app_site_edit', ['id' => $site->getId()]);
        }

        return $this->render('site/new.html.twig', [
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_site_show', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function show(Site $site, PageScanRepository $pageScans): Response
    {
        $this->denyAccessUnlessGranted(SiteVoter::VIEW, $site);

        return $this->render('site/show.html.twig', [
            'site' => $site,
            'latestPageScan' => $pageScans->findLatestForSite($site),
        ]);
    }

    #[Route('/{id}/edit', name: 'app_site_edit', methods: ['GET', 'POST'], requirements: ['id' => '\d+'])]
    public function edit(Request $request, Site $site, SiteRepository $siteRepository, AlertEvaluator $alertEvaluator): Response
    {
        $this->denyAccessUnlessGranted(SiteVoter::EDIT, $site);

        $form = $this->createForm(SiteVersionType::class, $site);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Recompute alerts right away with the entered technology/version.
            $report = $alertEvaluator->evaluate($site, flush: false);
            $siteRepository->save($site);

            if ($report->manualVersionInvalid) {
                $this->addFlash('error', $this->manualVersionError($site));
            } elseif ($site->isReadyForScan()) {
                $this->addFlash('success', sprintf('%s %s enregistré. Alertes recalculées.', $site->getEffectiveTechnology()?->label() ?? '', $site->getEffectiveVersion() ?? ''));
            } else {
                $this->addFlash('success', 'Enregistré. Renseignez la technologie et la version pour activer le scan.');
            }

            return $this->redirectToRoute('app_site_show', ['id' => $site->getId()]);
        }

        return $this->render('site/edit.html.twig', [
            'site' => $site,
            'form' => $form,
        ]);
    }

    #[Route('/{id}/scan', name: 'app_site_scan', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function scan(Request $request, Site $site, SiteMonitor $monitor): Response
    {
        $this->denyAccessUnlessGranted(SiteVoter::EDIT, $site);
        $this->validateCsrf($request, 'scan'.$site->getId());

        if (!$site->isReadyForScan()) {
            $this->addFlash('error', 'Renseignez la technologie et la version avant de lancer un scan.');

            return $this->redirectToRoute('app_site_show', ['id' => $site->getId()]);
        }

        $report = $monitor->refresh($site);
        if ($report->manualVersionInvalid) {
            $this->addFlash('error', $this->manualVersionError($site));
        } else {
            $this->addFlash('success', $site->getLastScanMessage() ?? 'Scan effectué.');
        }

        return $this->redirectToRoute('app_site_show', ['id' => $site->getId()]);
    }

    #[Route('/{id}/pages', name: 'app_site_pages', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function pages(Site $site, PageScanRepository $pageScans): Response
    {
        $this->denyAccessUnlessGranted(SiteVoter::VIEW, $site);

        $history = $pageScans->findHistoryForSite($site);

        return $this->render('site/pages.html.twig', [
            'site' => $site,
            'history' => $history,
            'latest' => $history[0] ?? null,
        ]);
    }

    #[Route('/{id}/pages/scan', name: 'app_site_page_scan', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function pageScan(Request $request, Site $site, SitemapScanner $scanner, PageScanRepository $pageScans): Response
    {
        $this->denyAccessUnlessGranted(SiteVoter::EDIT, $site);
        $this->validateCsrf($request, 'pagescan'.$site->getId());

        $result = $scanner->scan($site->getUrl());

        $scan = new PageScan($site);
        $scan->setNote($result->note);
        foreach ($result->pages as $page) {
            $scan->addResult(new PageResult($scan, $page['url'], $page['status']));
        }
        $pageScans->save($scan);

        if ([] === $result->pages) {
            $this->addFlash('error', $result->note ?? 'Aucune page n\'a pu être vérifiée.');
        } else {
            $this->addFlash('success', sprintf(
                '%d page(s) vérifiée(s) : %d OK, %d en erreur.',
                $scan->getTotalPages(),
                $scan->getOkCount(),
                $scan->getErrorCount(),
            ));
        }

        return $this->redirectToRoute('app_site_pages', ['id' => $site->getId()]);
    }

    #[Route('/{id}', name: 'app_site_delete', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function delete(Request $request, Site $site, SiteRepository $siteRepository): Response
    {
        $this->denyAccessUnlessGranted(SiteVoter::DELETE, $site);
        $this->validateCsrf($request, 'delete'.$site->getId());

        $siteRepository->remove($site);
        $this->addFlash('success', 'Site supprimé.');

        return $this->redirectToRoute('app_site_index');
    }

    private function validateCsrf(Request $request, string $id): void
    {
        if (!$this->isCsrfTokenValid($id, (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Jeton CSRF invalide.');
        }
    }

    private function manualVersionError(Site $site): string
    {
        return sprintf(
            "La version manuelle « %s » n'existe pas pour %s. Vérifiez la version saisie : les alertes CVE n'ont pas été recalculées.",
            $site->getManualVersion() ?? '',
            $site->getEffectiveTechnology()?->label() ?? 'cette technologie',
        );
    }
}

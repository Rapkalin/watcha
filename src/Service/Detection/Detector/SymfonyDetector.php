<?php

declare(strict_types=1);

namespace App\Service\Detection\Detector;

use App\Enum\Technology;
use App\Service\Detection\DetectionResult;
use App\Service\Detection\FetchedPage;
use App\Service\Detection\TechnologyDetectorInterface;

/**
 * Detects Symfony from signals that survive in production.
 *
 * Modern Symfony front-ends ship AssetMapper + Symfony UX (Stimulus/Turbo), which leave very
 * recognisable, public fingerprints in the HTML: an `importmap` referencing `@symfony/...`
 * packages and hash-versioned `/assets/*-XXXXXXXX.js|css` files (also advertised in a `Link:
 * rel=preload` header). Debug headers and the session cookie remain as weaker fallbacks.
 *
 * Symfony never exposes its version publicly, so the detected version stays null.
 */
final class SymfonyDetector implements TechnologyDetectorInterface
{
    public function detect(FetchedPage $page): ?DetectionResult
    {
        $body = $page->body;
        $cookies = implode('; ', $page->headers['set-cookie'] ?? []);
        $linkHeader = (string) $page->header('link');

        // 1) Symfony UX (Stimulus/Turbo) via importmap — unambiguous.
        if (str_contains($body, '@symfony/stimulus-bundle')
            || str_contains($body, '@symfony/ux-')
            || str_contains($body, '/assets/@symfony/')) {
            return new DetectionResult(Technology::SYMFONY, null, 'Symfony UX (importmap @symfony/*)', 92);
        }

        // 2) Debug headers — only present in dev, but a near-certain Symfony tell.
        if (null !== $page->header('x-debug-token') || null !== $page->header('x-debug-token-link')) {
            return new DetectionResult(Technology::SYMFONY, null, 'X-Debug-Token header', 90);
        }

        // 3) AssetMapper fingerprint: an importmap plus hash-versioned /assets/ files.
        $hasImportmap = false !== stripos($body, 'type="importmap"') || str_contains($body, 'es-module-shims');
        $hasHashedAsset = (bool) preg_match('#/assets/[\w./@-]+-[A-Za-z0-9_-]{6,}\.(?:js|css)#', $body)
            || (bool) preg_match('#/assets/[\w./@-]+-[A-Za-z0-9_-]{6,}\.(?:js|css)#', $linkHeader);
        if ($hasImportmap && $hasHashedAsset) {
            return new DetectionResult(Technology::SYMFONY, null, 'AssetMapper importmap + hashed assets', 80);
        }

        // 4) Weaker fallbacks.
        if (false !== stripos((string) $page->header('x-powered-by'), 'Symfony')) {
            return new DetectionResult(Technology::SYMFONY, null, 'X-Powered-By header', 70);
        }
        if (preg_match('/\bSYMFONY[0-9A-Z]*=|sf_redirect=/', $cookies)) {
            return new DetectionResult(Technology::SYMFONY, null, 'Symfony session cookie', 45);
        }

        return null;
    }
}

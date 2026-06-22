<?php

declare(strict_types=1);

namespace App\Service\Detection\Detector;

use App\Enum\Technology;
use App\Service\Detection\DetectionResult;
use App\Service\Detection\FetchedPage;
use App\Service\Detection\TechnologyDetectorInterface;

/**
 * Laravel does not advertise its version publicly. We can only infer the framework from the
 * session/CSRF cookies it sets, so the detected version stays null (update alerts rely on it
 * being filled in manually or by a future authenticated probe).
 */
final class LaravelDetector implements TechnologyDetectorInterface
{
    public function detect(FetchedPage $page): ?DetectionResult
    {
        $cookies = implode('; ', $page->headers['set-cookie'] ?? []);

        $isLaravel = str_contains($cookies, 'laravel_session')
            || str_contains($cookies, 'XSRF-TOKEN')
            || false !== stripos((string) $page->header('x-powered-by'), 'Laravel');

        if (!$isLaravel) {
            return null;
        }

        return new DetectionResult(Technology::LARAVEL, null, 'laravel_session / XSRF-TOKEN cookie', 60);
    }
}

<?php

declare(strict_types=1);

namespace App\Service\Detection\Detector;

use App\Enum\Technology;
use App\Service\Detection\DetectionResult;
use App\Service\Detection\FetchedPage;
use App\Service\Detection\TechnologyDetectorInterface;

final class DrupalDetector implements TechnologyDetectorInterface
{
    public function detect(FetchedPage $page): ?DetectionResult
    {
        $generator = $page->header('x-generator') ?? '';
        $body = $page->body;

        $isDrupal = false !== stripos($generator, 'Drupal')
            || str_contains($body, 'sites/default/files')
            || str_contains($body, '/core/misc/drupal.js')
            || str_contains($body, 'drupalSettings')
            || str_contains($body, 'data-drupal-')
            || str_contains($body, '/sites/all/')
            || str_contains($body, '/core/themes/')
            || (bool) preg_match('/<meta[^>]+name=["\']Generator["\'][^>]+content=["\']Drupal/i', $body);

        if (!$isDrupal) {
            return null;
        }

        // The X-Generator header / generator meta expose the major version, e.g. "Drupal 10 (https://www.drupal.org)".
        if (preg_match('/Drupal\s+(\d+(?:\.\d+)*)/i', $generator, $m)
            || preg_match('/<meta[^>]+name=["\']Generator["\'][^>]+content=["\']Drupal\s+(\d+(?:\.\d+)*)/i', $body, $m)) {
            return new DetectionResult(Technology::DRUPAL, $m[1], 'X-Generator / generator meta', 80);
        }

        return new DetectionResult(Technology::DRUPAL, null, 'Drupal asset paths', 55);
    }
}

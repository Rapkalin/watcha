<?php

declare(strict_types=1);

namespace App\Service\Detection;

/**
 * Detects whether a fetched page is built with a given technology, and which version.
 *
 * Implementations are tagged "app.technology_detector" and tried in turn by the
 * {@see SiteScanner}; the highest-confidence result wins.
 */
interface TechnologyDetectorInterface
{
    /**
     * @return DetectionResult|null null when this detector does not recognise the page
     */
    public function detect(FetchedPage $page): ?DetectionResult;
}

<?php

declare(strict_types=1);

namespace App\Service\Cve;

use App\Enum\Technology;

/**
 * A source of security advisories for one or more technologies.
 *
 * Implementations are tagged "app.advisory_provider" and collected by the
 * {@see AdvisorySynchronizer}.
 */
interface AdvisoryProviderInterface
{
    public function supports(Technology $technology): bool;

    /**
     * Fetches the current set of advisories for the given technology.
     *
     * @return iterable<AdvisoryDto>
     *
     * @throws AdvisoryFetchException when the upstream feed cannot be retrieved/parsed
     */
    public function fetch(Technology $technology): iterable;
}

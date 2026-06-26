<?php

declare(strict_types=1);

namespace App\Service\Version;

use App\Enum\Technology;

interface LatestVersionResolverInterface
{
    /**
     * @return string|null the latest stable version, or null if it cannot be resolved
     */
    public function latestStable(Technology $technology): ?string;

    /**
     * Whether $version is a published release of $technology.
     *
     * @return bool|null true/false when the catalogue could be resolved, null when it could not
     *                   (network error, unknown source) so callers can avoid a false negative
     */
    public function versionExists(Technology $technology, string $version): ?bool;
}

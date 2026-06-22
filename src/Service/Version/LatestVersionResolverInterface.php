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
}

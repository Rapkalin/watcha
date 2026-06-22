<?php

declare(strict_types=1);

namespace App\Service\Detection;

use App\Enum\Technology;

final readonly class DetectionResult
{
    public function __construct(
        public Technology $technology,
        public ?string $version,
        /** How the technology/version was found, surfaced to the user for transparency. */
        public string $evidence,
        /** 0..100 — used to pick the best result when several detectors match. */
        public int $confidence = 50,
    ) {
    }
}

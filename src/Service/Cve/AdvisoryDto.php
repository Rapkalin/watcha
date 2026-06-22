<?php

declare(strict_types=1);

namespace App\Service\Cve;

use App\Enum\Severity;
use App\Enum\Technology;
use DateTimeImmutable;

/**
 * Normalised representation of a single advisory as returned by a provider,
 * before it is persisted as an {@see \App\Entity\Advisory}.
 */
final readonly class AdvisoryDto
{
    public function __construct(
        public Technology $technology,
        public string $source,
        public string $externalId,
        public string $title,
        public Severity $severity = Severity::UNKNOWN,
        public ?string $cveId = null,
        public ?string $summary = null,
        public ?string $affectedConstraint = null,
        public ?string $fixedVersion = null,
        public ?string $referenceUrl = null,
        public ?DateTimeImmutable $publishedAt = null,
    ) {
    }
}

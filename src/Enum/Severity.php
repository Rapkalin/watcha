<?php

declare(strict_types=1);

namespace App\Enum;

enum Severity: string
{
    case UNKNOWN = 'unknown';
    case LOW = 'low';
    case MEDIUM = 'medium';
    case HIGH = 'high';
    case CRITICAL = 'critical';

    public function label(): string
    {
        return ucfirst($this->value);
    }

    /**
     * Numeric weight used to sort and to keep the highest severity when merging.
     */
    public function weight(): int
    {
        return match ($this) {
            self::UNKNOWN => 0,
            self::LOW => 1,
            self::MEDIUM => 2,
            self::HIGH => 3,
            self::CRITICAL => 4,
        };
    }

    /**
     * Maps a CVSS v3 base score to a severity bucket.
     */
    public static function fromCvssScore(?float $score): self
    {
        return match (true) {
            null === $score => self::UNKNOWN,
            $score >= 9.0 => self::CRITICAL,
            $score >= 7.0 => self::HIGH,
            $score >= 4.0 => self::MEDIUM,
            $score > 0.0 => self::LOW,
            default => self::UNKNOWN,
        };
    }

    /**
     * Normalises the textual severity used by GHSA / OSV ("MODERATE", etc.).
     */
    public static function fromString(?string $value): self
    {
        return match (strtoupper((string) $value)) {
            'CRITICAL' => self::CRITICAL,
            'HIGH' => self::HIGH,
            'MODERATE', 'MEDIUM' => self::MEDIUM,
            'LOW' => self::LOW,
            default => self::UNKNOWN,
        };
    }
}

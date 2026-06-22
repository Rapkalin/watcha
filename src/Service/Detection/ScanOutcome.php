<?php

declare(strict_types=1);

namespace App\Service\Detection;

final readonly class ScanOutcome
{
    public function __construct(
        public bool $reachable,
        public ?DetectionResult $detection,
        public string $message,
    ) {
    }

    public static function unreachable(string $message): self
    {
        return new self(false, null, $message);
    }

    public static function detected(DetectionResult $result): self
    {
        $version = $result->version ?? 'version inconnue';

        return new self(true, $result, sprintf('%s détecté (%s) via %s.', $result->technology->label(), $version, $result->evidence));
    }

    public static function undetected(): self
    {
        return new self(true, null, 'Site accessible mais aucune technologie connue détectée.');
    }
}

<?php

declare(strict_types=1);

namespace App\Service\Cve;

use App\Enum\Technology;

/**
 * Mutable tally of an advisory synchronisation run.
 */
final class SyncReport
{
    public int $created = 0;
    public int $updated = 0;

    /** @var array<int, array{technology: string, message: string}> */
    public array $errors = [];

    public function addError(Technology $technology, string $message): void
    {
        $this->errors[] = ['technology' => $technology->value, 'message' => $message];
    }

    public function total(): int
    {
        return $this->created + $this->updated;
    }

    public function hasErrors(): bool
    {
        return [] !== $this->errors;
    }
}

<?php

declare(strict_types=1);

namespace App\Service\Alert;

final class AlertReport
{
    public int $created = 0;
    public int $reopened = 0;
    public int $resolved = 0;

    /** True when a manually entered version does not match any published release of the technology. */
    public bool $manualVersionInvalid = false;

    public function changed(): int
    {
        return $this->created + $this->reopened + $this->resolved;
    }
}

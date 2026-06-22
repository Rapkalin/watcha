<?php

declare(strict_types=1);

namespace App\Service\Alert;

final class AlertReport
{
    public int $created = 0;
    public int $reopened = 0;
    public int $resolved = 0;

    public function changed(): int
    {
        return $this->created + $this->reopened + $this->resolved;
    }
}

<?php

declare(strict_types=1);

namespace App\Service;

use App\Service\Alert\AlertReport;
use App\Service\Detection\ScanOutcome;

final readonly class MonitorResult
{
    public function __construct(
        public ScanOutcome $scan,
        public AlertReport $alerts,
    ) {
    }
}

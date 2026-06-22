<?php

declare(strict_types=1);

namespace App\Enum;

enum AlertType: string
{
    /** A published advisory affects the detected version of a monitored site. */
    case CVE = 'cve';

    /** A newer (patch/minor/major) version is available for the detected technology. */
    case UPDATE_AVAILABLE = 'update_available';

    public function label(): string
    {
        return match ($this) {
            self::CVE => 'Vulnérabilité (CVE)',
            self::UPDATE_AVAILABLE => 'Mise à jour disponible',
        };
    }
}

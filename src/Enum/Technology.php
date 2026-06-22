<?php

declare(strict_types=1);

namespace App\Enum;

/**
 * The CMS / frameworks monitored by the dashboard.
 */
enum Technology: string
{
    case SYMFONY = 'symfony';
    case LARAVEL = 'laravel';
    case DRUPAL = 'drupal';
    case WORDPRESS = 'wordpress';

    public function label(): string
    {
        return match ($this) {
            self::SYMFONY => 'Symfony',
            self::LARAVEL => 'Laravel',
            self::DRUPAL => 'Drupal',
            self::WORDPRESS => 'WordPress',
        };
    }

    /**
     * OSV.dev ecosystem name, or null when the technology is not covered by OSV's
     * Packagist data and must be handled by a dedicated provider (e.g. WordPress core).
     */
    public function osvEcosystem(): ?string
    {
        return match ($this) {
            self::SYMFONY, self::LARAVEL, self::DRUPAL => 'Packagist',
            self::WORDPRESS => null,
        };
    }

    /**
     * Canonical Composer package used to query OSV.dev for this technology's core.
     */
    public function osvPackage(): ?string
    {
        return match ($this) {
            self::SYMFONY => 'symfony/symfony',
            self::LARAVEL => 'laravel/framework',
            self::DRUPAL => 'drupal/core',
            self::WORDPRESS => null,
        };
    }

    /**
     * @return self[]
     */
    public static function all(): array
    {
        return self::cases();
    }
}

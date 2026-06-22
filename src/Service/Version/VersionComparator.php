<?php

declare(strict_types=1);

namespace App\Service\Version;

use Composer\Semver\Comparator;
use Composer\Semver\Semver;
use Composer\Semver\VersionParser;
use Psr\Log\LoggerInterface;
use UnexpectedValueException;

/**
 * Thin wrapper around composer/semver to compare detected versions against advisory constraints.
 */
final class VersionComparator
{
    private VersionParser $parser;

    public function __construct(private readonly LoggerInterface $logger)
    {
        $this->parser = new VersionParser();
    }

    /**
     * Whether $version falls within the (Composer-style) $constraint of affected versions.
     * Unparseable input is treated as "not affected" to avoid false positives.
     */
    public function isAffected(string $version, ?string $constraint): bool
    {
        if (null === $constraint || '' === $constraint) {
            return false;
        }

        $normalized = $this->normalize($version);
        if (null === $normalized) {
            return false;
        }

        try {
            return Semver::satisfies($normalized, $constraint);
        } catch (UnexpectedValueException $e) {
            $this->logger->debug('Unparseable advisory constraint', [
                'version' => $version,
                'constraint' => $constraint,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * True when $latest is strictly greater than $current.
     */
    public function isOutdated(string $current, string $latest): bool
    {
        $a = $this->normalize($current);
        $b = $this->normalize($latest);
        if (null === $a || null === $b) {
            return false;
        }

        try {
            return Comparator::lessThan($a, $b);
        } catch (UnexpectedValueException) {
            return false;
        }
    }

    /**
     * Normalises a loose version string (e.g. "6.4", "v10.3.1") into a comparable form.
     */
    private function normalize(string $version): ?string
    {
        $version = ltrim(trim($version), 'vV');
        if ('' === $version) {
            return null;
        }

        try {
            return $this->parser->normalize($version);
        } catch (UnexpectedValueException) {
            // Pad short versions like "6.4" to "6.4.0" and retry.
            if (preg_match('/^\d+\.\d+$/', $version)) {
                try {
                    return $this->parser->normalize($version.'.0');
                } catch (UnexpectedValueException) {
                    return null;
                }
            }

            return null;
        }
    }
}

<?php

declare(strict_types=1);

namespace App\Service\Version;

use App\Enum\Technology;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;
use Symfony\Contracts\HttpClient\Exception\ExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Resolves the latest stable release of a technology from its canonical source
 * (Packagist for Composer packages, wordpress.org for WordPress core). Results are cached.
 */
final class LatestVersionResolver implements LatestVersionResolverInterface
{
    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly CacheInterface $cache,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function latestStable(Technology $technology): ?string
    {
        if (Technology::WORDPRESS === $technology) {
            return $this->cache->get('latest_version_wordpress', function (ItemInterface $item): ?string {
                $item->expiresAfter(3600);

                return $this->wordPressLatest();
            });
        }

        // Packagist releases are returned newest-first, so the first stable one is the latest.
        $versions = $this->knownVersions($technology);

        return $versions[0] ?? null;
    }

    public function versionExists(Technology $technology, string $version): ?bool
    {
        $needle = ltrim(trim($version), 'vV');
        if ('' === $needle) {
            return false;
        }

        $known = $this->knownVersions($technology);
        if (null === $known) {
            // Catalogue unreachable: report "unknown" rather than a false negative.
            return null;
        }

        foreach ($known as $candidate) {
            // Exact match, or a partial version the user pinned (e.g. "6.4" matches "6.4.2").
            if ($candidate === $needle || str_starts_with($candidate, $needle.'.')) {
                return true;
            }
        }

        return false;
    }

    /**
     * Every published stable version of a technology, newest-first, normalised without a leading "v".
     *
     * @return list<string>|null null when the source could not be reached
     */
    private function knownVersions(Technology $technology): ?array
    {
        return $this->cache->get(
            'known_versions_'.$technology->value,
            function (ItemInterface $item) use ($technology): ?array {
                $item->expiresAfter(3600);

                return match ($technology) {
                    Technology::WORDPRESS => $this->wordPressVersions(),
                    default => $this->packagistVersions($technology),
                };
            }
        );
    }

    /**
     * @return list<string>|null
     */
    private function packagistVersions(Technology $technology): ?array
    {
        $package = $technology->osvPackage();
        if (null === $package) {
            return null;
        }

        try {
            $data = $this->httpClient
                ->request('GET', sprintf('https://repo.packagist.org/p2/%s.json', $package))
                ->toArray();
        } catch (ExceptionInterface $e) {
            $this->logger->warning('Packagist version lookup failed', ['package' => $package, 'error' => $e->getMessage()]);

            return null;
        }

        $versions = [];
        foreach ($data['packages'][$package] ?? [] as $release) {
            $version = $release['version'] ?? null;
            if (is_string($version) && $this->isStable($version)) {
                $versions[] = ltrim($version, 'vV');
            }
        }

        return $versions;
    }

    /**
     * @return list<string>|null
     */
    private function wordPressVersions(): ?array
    {
        try {
            $data = $this->httpClient
                ->request('GET', 'https://api.wordpress.org/core/stable-check/1.0/')
                ->toArray();
        } catch (ExceptionInterface $e) {
            $this->logger->warning('wordpress.org version list lookup failed', ['error' => $e->getMessage()]);

            return null;
        }

        // The endpoint returns a { "version": "latest|outdated|insecure" } map.
        return array_map('strval', array_keys($data));
    }

    private function wordPressLatest(): ?string
    {
        try {
            $data = $this->httpClient
                ->request('GET', 'https://api.wordpress.org/core/version-check/1.7/')
                ->toArray();
        } catch (ExceptionInterface $e) {
            $this->logger->warning('wordpress.org version lookup failed', ['error' => $e->getMessage()]);

            return null;
        }

        $current = $data['offers'][0]['current'] ?? null;

        return is_string($current) ? $current : null;
    }

    private function isStable(string $version): bool
    {
        return !preg_match('/-(dev|alpha|beta|rc)/i', $version);
    }
}

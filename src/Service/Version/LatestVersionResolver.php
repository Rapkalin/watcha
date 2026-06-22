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
        return $this->cache->get(
            'latest_version_'.$technology->value,
            function (ItemInterface $item) use ($technology): ?string {
                $item->expiresAfter(3600);

                return match ($technology) {
                    Technology::WORDPRESS => $this->wordPressLatest(),
                    default => $this->packagistLatest($technology),
                };
            }
        );
    }

    private function packagistLatest(Technology $technology): ?string
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

        foreach ($data['packages'][$package] ?? [] as $release) {
            $version = $release['version'] ?? null;
            if (is_string($version) && $this->isStable($version)) {
                return ltrim($version, 'vV');
            }
        }

        return null;
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

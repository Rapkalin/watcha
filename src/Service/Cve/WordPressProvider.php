<?php

declare(strict_types=1);

namespace App\Service\Cve;

use App\Enum\Severity;
use App\Enum\Technology;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\Exception\ExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * WordPress core is not part of OSV's Packagist data, so advisories come from WPScan
 * (https://wpscan.com/api), which is keyed by version and requires an API token.
 *
 * Without a token this provider yields nothing (update-availability alerting still works via the
 * wordpress.org version feed used by {@see \App\Service\Version\LatestVersionResolver}).
 */
final class WordPressProvider implements AdvisoryProviderInterface
{
    public const SOURCE = 'wpscan';

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly LoggerInterface $logger,
        private readonly string $wpscanApiToken = '',
    ) {
    }

    public function supports(Technology $technology): bool
    {
        return Technology::WORDPRESS === $technology;
    }

    public function fetch(Technology $technology): iterable
    {
        if ('' === $this->wpscanApiToken) {
            $this->logger->notice('WordPress advisory sync skipped: no WPSCAN_API_TOKEN configured.');

            return [];
        }

        // WPScan exposes vulnerabilities per WordPress release. We sync the advisories of the
        // currently supported branches; matching against a site's detected version is then handled
        // by the AlertEvaluator using the affected constraint built here.
        try {
            $response = $this->httpClient->request('GET', 'https://wpscan.com/api/v3/wordpresses/'.$this->latestBranchSlug(), [
                'headers' => ['Authorization' => 'Token token='.$this->wpscanApiToken],
            ]);
            $data = $response->toArray();
        } catch (ExceptionInterface $e) {
            throw new AdvisoryFetchException('WPScan request failed: '.$e->getMessage(), 0, $e);
        }

        foreach ($data as $version => $payload) {
            foreach ($payload['vulnerabilities'] ?? [] as $vuln) {
                $dto = $this->mapVuln((string) $version, $vuln);
                if (null !== $dto) {
                    yield $dto;
                }
            }
        }
    }

    private function latestBranchSlug(): string
    {
        // WPScan's per-release endpoint expects a slug such as "693" for 6.9.3. The caller may
        // override this in a future iteration; we default to the latest known stable branch.
        return '690';
    }

    /**
     * @param array<string, mixed> $vuln
     */
    private function mapVuln(string $affectedVersion, array $vuln): ?AdvisoryDto
    {
        $id = $vuln['id'] ?? null;
        if (null === $id) {
            return null;
        }

        $fixedVersion = $vuln['fixed_in'] ?? null;
        $cve = null;
        foreach ($vuln['references']['cve'] ?? [] as $cveId) {
            $cve = 'CVE-'.ltrim((string) $cveId, 'CVE-');
            break;
        }

        return new AdvisoryDto(
            technology: Technology::WORDPRESS,
            source: self::SOURCE,
            externalId: (string) $id,
            title: (string) ($vuln['title'] ?? ('WordPress vulnerability '.$id)),
            severity: Severity::fromCvssScore(isset($vuln['cvss']['score']) ? (float) $vuln['cvss']['score'] : null),
            cveId: $cve,
            summary: $vuln['description'] ?? null,
            affectedConstraint: null !== $fixedVersion ? '<'.$fixedVersion : null,
            fixedVersion: null !== $fixedVersion ? (string) $fixedVersion : null,
            referenceUrl: $vuln['references']['url'][0] ?? null,
            publishedAt: null,
        );
    }
}

<?php

declare(strict_types=1);

namespace App\Service\Cve;

use App\Enum\Severity;
use App\Enum\Technology;
use DateTimeImmutable;
use Exception;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpClient\Exception\TransportException;
use Symfony\Contracts\HttpClient\Exception\ExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Fetches advisories from the OSV.dev database (https://osv.dev) for technologies whose core
 * is published on Packagist (Symfony, Laravel, Drupal).
 */
final class OsvProvider implements AdvisoryProviderInterface
{
    private const ENDPOINT = 'https://api.osv.dev/v1/query';
    public const SOURCE = 'osv.dev';

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function supports(Technology $technology): bool
    {
        return null !== $technology->osvEcosystem() && null !== $technology->osvPackage();
    }

    public function fetch(Technology $technology): iterable
    {
        $ecosystem = $technology->osvEcosystem();
        $package = $technology->osvPackage();
        if (null === $ecosystem || null === $package) {
            return [];
        }

        try {
            $response = $this->httpClient->request('POST', self::ENDPOINT, [
                'json' => ['package' => ['ecosystem' => $ecosystem, 'name' => $package]],
            ]);
            $data = $response->toArray();
        } catch (TransportException $e) {
            throw new AdvisoryFetchException(sprintf('OSV.dev unreachable for %s: %s', $package, $e->getMessage()), 0, $e);
        } catch (ExceptionInterface $e) {
            throw new AdvisoryFetchException(sprintf('OSV.dev returned an error for %s: %s', $package, $e->getMessage()), 0, $e);
        }

        foreach ($data['vulns'] ?? [] as $vuln) {
            $dto = $this->mapVuln($technology, $package, $vuln);
            if (null !== $dto) {
                yield $dto;
            }
        }
    }

    /**
     * @param array<string, mixed> $vuln
     */
    private function mapVuln(Technology $technology, string $package, array $vuln): ?AdvisoryDto
    {
        $id = $vuln['id'] ?? null;
        if (!is_string($id) || '' === $id) {
            return null;
        }

        $aliases = array_values(array_filter(
            $vuln['aliases'] ?? [],
            static fn ($a) => is_string($a) && str_starts_with($a, 'CVE-'),
        ));

        [$constraint, $fixedVersion] = $this->extractRange($package, $vuln['affected'] ?? []);

        return new AdvisoryDto(
            technology: $technology,
            source: self::SOURCE,
            externalId: $id,
            title: $this->shorten((string) ($vuln['summary'] ?? $vuln['details'] ?? $id)),
            severity: $this->extractSeverity($vuln),
            cveId: $aliases[0] ?? null,
            summary: isset($vuln['details']) ? (string) $vuln['details'] : null,
            affectedConstraint: $constraint,
            fixedVersion: $fixedVersion,
            referenceUrl: $this->extractReference($vuln, $id),
            publishedAt: $this->parseDate($vuln['published'] ?? null),
        );
    }

    /**
     * Builds a Composer-style constraint from the OSV "affected" ranges that match the package.
     *
     * @param array<int, array<string, mixed>> $affected
     *
     * @return array{0: ?string, 1: ?string} [constraint, firstFixedVersion]
     */
    private function extractRange(string $package, array $affected): array
    {
        $pieces = [];
        $firstFixed = null;

        foreach ($affected as $entry) {
            if (($entry['package']['name'] ?? null) !== $package) {
                continue;
            }
            foreach ($entry['ranges'] ?? [] as $range) {
                $introduced = null;
                $fixed = null;
                foreach ($range['events'] ?? [] as $event) {
                    if (isset($event['introduced'])) {
                        $introduced = (string) $event['introduced'];
                    }
                    if (isset($event['fixed'])) {
                        $fixed = (string) $event['fixed'];
                    }
                }

                $part = [];
                if (null !== $introduced && '0' !== $introduced) {
                    $part[] = '>='.$introduced;
                }
                if (null !== $fixed) {
                    $part[] = '<'.$fixed;
                    $firstFixed ??= $fixed;
                }
                if ([] !== $part) {
                    $pieces[] = implode(',', $part);
                }
            }
        }

        $constraint = [] === $pieces ? null : implode(' || ', array_unique($pieces));

        return [$constraint, $firstFixed];
    }

    /**
     * @param array<string, mixed> $vuln
     */
    private function extractSeverity(array $vuln): Severity
    {
        // GHSA-backed OSV records expose a textual severity ("CRITICAL", "MODERATE"...).
        // OSV's "severity" array only carries a CVSS *vector* (not a numeric base score), which we
        // cannot turn into a bucket cheaply, so the textual field is our single source here.
        $textual = $vuln['database_specific']['severity'] ?? null;

        return is_string($textual) ? Severity::fromString($textual) : Severity::UNKNOWN;
    }

    /**
     * @param array<string, mixed> $vuln
     */
    private function extractReference(array $vuln, string $id): string
    {
        foreach ($vuln['references'] ?? [] as $ref) {
            if (($ref['type'] ?? null) === 'ADVISORY' && isset($ref['url'])) {
                return (string) $ref['url'];
            }
        }
        foreach ($vuln['references'] ?? [] as $ref) {
            if (isset($ref['url'])) {
                return (string) $ref['url'];
            }
        }

        return 'https://osv.dev/vulnerability/'.$id;
    }

    private function parseDate(?string $value): ?DateTimeImmutable
    {
        if (!is_string($value) || '' === $value) {
            return null;
        }

        try {
            return new DateTimeImmutable($value);
        } catch (Exception $e) {
            $this->logger->debug('Unparseable OSV date', ['value' => $value, 'error' => $e->getMessage()]);

            return null;
        }
    }

    private function shorten(string $text): string
    {
        $text = trim(preg_replace('/\s+/', ' ', $text) ?? $text);

        return mb_strlen($text) > 255 ? mb_substr($text, 0, 252).'…' : $text;
    }
}

<?php

declare(strict_types=1);

namespace App\Service\Page;

use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\Exception\ExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Reads a site's /sitemap.xml (following a sitemap index one level deep) and checks the HTTP status
 * of every listed page. Runs the checks concurrently and uses the SSRF-safe HTTP client, so it can
 * never be turned into a probe of private/internal addresses.
 */
final class SitemapScanner
{
    private const int MAX_URLS = 200;
    private const int MAX_SUB_SITEMAPS = 10;

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function scan(string $baseUrl): SitemapScanResult
    {
        $baseUrl = rtrim($baseUrl, '/');

        $body = $this->fetchBody($baseUrl.'/sitemap.xml');
        if (null === $body) {
            return new SitemapScanResult('Aucun sitemap.xml accessible sur ce site.', []);
        }

        $urls = $this->collectUrls($body, $baseUrl);
        if ([] === $urls) {
            return new SitemapScanResult('Le sitemap.xml ne contient aucune URL exploitable.', []);
        }

        $truncated = count($urls) > self::MAX_URLS;
        $urls = array_slice($urls, 0, self::MAX_URLS);

        $pages = $this->checkStatuses($urls);

        $note = $truncated
            ? sprintf('Sitemap volumineux : seules les %d premières pages ont été vérifiées.', self::MAX_URLS)
            : null;

        return new SitemapScanResult($note, $pages);
    }

    /**
     * Extracts page URLs from a sitemap, descending into a sitemap index once.
     *
     * @return list<string>
     */
    private function collectUrls(string $body, string $baseUrl): array
    {
        // A sitemap index points to other sitemaps; a urlset lists the pages directly.
        if (false !== stripos($body, '<sitemapindex')) {
            $urls = [];
            foreach (array_slice($this->extractLocs($body), 0, self::MAX_SUB_SITEMAPS) as $subSitemap) {
                $subBody = $this->fetchBody($subSitemap);
                if (null !== $subBody) {
                    $urls = array_merge($urls, $this->extractLocs($subBody));
                }
                if (count($urls) >= self::MAX_URLS) {
                    break;
                }
            }
        } else {
            $urls = $this->extractLocs($body);
        }

        return $this->keepSameSite($urls, $baseUrl);
    }

    /**
     * Pulls <loc> values out of sitemap XML with a regex, which sidesteps XML entity processing
     * (no XXE) and namespace quirks entirely.
     *
     * @return list<string>
     */
    private function extractLocs(string $xml): array
    {
        if (!preg_match_all('#<loc>\s*(.*?)\s*</loc>#is', $xml, $matches)) {
            return [];
        }

        $locs = [];
        foreach ($matches[1] as $loc) {
            $loc = html_entity_decode(trim($loc), \ENT_QUOTES | \ENT_XML1);
            if ('' !== $loc) {
                $locs[] = $loc;
            }
        }

        return $locs;
    }

    /**
     * Keeps only http(s) URLs that belong to the same site (ignoring a leading "www."), de-duplicated.
     *
     * @param list<string> $urls
     *
     * @return list<string>
     */
    private function keepSameSite(array $urls, string $baseUrl): array
    {
        $baseHost = $this->normaliseHost((string) parse_url($baseUrl, \PHP_URL_HOST));

        $kept = [];
        foreach (array_unique($urls) as $url) {
            $scheme = parse_url($url, \PHP_URL_SCHEME);
            if (!in_array($scheme, ['http', 'https'], true)) {
                continue;
            }
            if ($this->normaliseHost((string) parse_url($url, \PHP_URL_HOST)) === $baseHost) {
                $kept[] = $url;
            }
        }

        return $kept;
    }

    private function normaliseHost(string $host): string
    {
        return preg_replace('/^www\./i', '', strtolower($host)) ?? $host;
    }

    /**
     * Issues concurrent requests and records the HTTP status of each, preserving input order.
     *
     * @param list<string> $urls
     *
     * @return list<array{url: string, status: int|null}>
     */
    private function checkStatuses(array $urls): array
    {
        /** @var array<int, string> $urlByResponseId */
        $urlByResponseId = [];
        $responses = [];
        $status = array_fill_keys($urls, null);

        foreach ($urls as $url) {
            try {
                $response = $this->httpClient->request('GET', $url);
                $responses[] = $response;
                $urlByResponseId[spl_object_id($response)] = $url;
            } catch (TransportExceptionInterface $e) {
                $this->logger->info('Page check could not start', ['url' => $url, 'error' => $e->getMessage()]);
            }
        }

        foreach ($this->httpClient->stream($responses) as $response => $chunk) {
            $url = $urlByResponseId[spl_object_id($response)] ?? null;
            if (null === $url) {
                continue;
            }

            try {
                if ($chunk->isFirst()) {
                    $status[$url] = $response->getStatusCode();
                    // We only need the status line — stop the transfer before downloading the body.
                    $response->cancel();
                }
            } catch (TransportExceptionInterface $e) {
                $status[$url] = null;
                $this->logger->info('Page check failed', ['url' => $url, 'error' => $e->getMessage()]);
            }
        }

        $pages = [];
        foreach ($urls as $url) {
            $pages[] = ['url' => $url, 'status' => $status[$url]];
        }

        return $pages;
    }

    private function fetchBody(string $url): ?string
    {
        try {
            $response = $this->httpClient->request('GET', $url);
            if ($response->getStatusCode() >= 400) {
                return null;
            }

            return $response->getContent(false);
        } catch (ExceptionInterface $e) {
            $this->logger->info('Sitemap fetch failed', ['url' => $url, 'error' => $e->getMessage()]);

            return null;
        }
    }
}

<?php

declare(strict_types=1);

namespace App\Service\Detection;

use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\Exception\ExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Fetches a site's homepage and runs every detector against it, returning the most confident match.
 */
final class SiteScanner
{
    /**
     * @param iterable<TechnologyDetectorInterface> $detectors
     */
    public function __construct(
        private readonly iterable $detectors,
        private readonly HttpClientInterface $httpClient,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function scan(string $url): ScanOutcome
    {
        $page = $this->fetch($url);
        if (null === $page) {
            return ScanOutcome::unreachable('Impossible de joindre le site (timeout, DNS ou erreur réseau).');
        }

        if ($page->statusCode >= 400) {
            return ScanOutcome::unreachable(sprintf('Le site a répondu avec le code HTTP %d.', $page->statusCode));
        }

        $best = null;
        foreach ($this->detectors as $detector) {
            $result = $detector->detect($page);
            if (null === $result) {
                continue;
            }
            if (null === $best || $result->confidence > $best->confidence) {
                $best = $result;
            }
        }

        return null !== $best ? ScanOutcome::detected($best) : ScanOutcome::undetected();
    }

    private function fetch(string $url): ?FetchedPage
    {
        try {
            $response = $this->httpClient->request('GET', $url, [
                'headers' => ['Accept' => 'text/html,application/xhtml+xml'],
            ]);
            $statusCode = $response->getStatusCode();
            $headers = $response->getHeaders(false);
            // Cap the body to 512 KiB — detection only needs the <head> and early markup.
            $body = substr($response->getContent(false), 0, 512 * 1024);
        } catch (ExceptionInterface $e) {
            $this->logger->info('Site scan fetch failed', ['url' => $url, 'error' => $e->getMessage()]);

            return null;
        }

        return new FetchedPage($url, $statusCode, $headers, $body);
    }
}

<?php

declare(strict_types=1);

namespace App\Service\Detection\Detector;

use App\Enum\Technology;
use App\Service\Detection\DetectionResult;
use App\Service\Detection\FetchedPage;
use App\Service\Detection\TechnologyDetectorInterface;
use Symfony\Contracts\HttpClient\Exception\ExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final class WordPressDetector implements TechnologyDetectorInterface
{
    public function __construct(private readonly HttpClientInterface $httpClient)
    {
    }

    public function detect(FetchedPage $page): ?DetectionResult
    {
        $body = $page->body;
        $linkHeader = (string) $page->header('link');

        $isWordPress = str_contains($body, '/wp-content/')
            || str_contains($body, '/wp-includes/')
            || str_contains($body, 'wp-emoji-release')
            || str_contains($body, '/wp-json/')
            || str_contains($linkHeader, 'wp-json')
            || str_contains($linkHeader, 'rel="https://api.w.org/"')
            || (bool) preg_match('/<meta[^>]+name=["\']generator["\'][^>]+content=["\']WordPress/i', $body);

        if (!$isWordPress) {
            return null;
        }

        // 1) Generator meta tag — cleanest source when not stripped.
        if (preg_match('/<meta[^>]+name=["\']generator["\'][^>]+content=["\']WordPress\s+([\d.]+)/i', $body, $m)) {
            return new DetectionResult(Technology::WORDPRESS, $m[1], 'meta generator tag', 92);
        }

        // 2) Version embedded in a core asset query string, e.g. /wp-includes/js/...?ver=6.4.2
        if (preg_match('#/wp-(?:includes|admin)/[^"\']+\?ver=([\d.]+)#i', $body, $m)) {
            return new DetectionResult(Technology::WORDPRESS, $m[1], 'wp-includes ?ver= asset', 75);
        }

        // 3) RSS feed generator, e.g. <generator>https://wordpress.org/?v=6.4.2</generator>
        $version = $this->versionFromFeed($page->url);
        if (null !== $version) {
            return new DetectionResult(Technology::WORDPRESS, $version, '/feed/ generator', 78);
        }

        // 4) readme.html (often present, sometimes removed by hardening).
        $version = $this->versionFromReadme($page->url);
        if (null !== $version) {
            return new DetectionResult(Technology::WORDPRESS, $version, '/readme.html', 70);
        }

        return new DetectionResult(Technology::WORDPRESS, null, 'wp-content / wp-json markers', 62);
    }

    private function versionFromFeed(string $baseUrl): ?string
    {
        try {
            $xml = $this->httpClient->request('GET', rtrim($baseUrl, '/').'/feed/')->getContent(false);
        } catch (ExceptionInterface) {
            return null;
        }

        if (preg_match('#<generator>https?://wordpress\.org/\?v=([\d.]+)#i', $xml, $m)) {
            return $m[1];
        }

        return null;
    }

    private function versionFromReadme(string $baseUrl): ?string
    {
        try {
            $html = $this->httpClient->request('GET', rtrim($baseUrl, '/').'/readme.html')->getContent(false);
        } catch (ExceptionInterface) {
            return null;
        }

        if (preg_match('/Version\s+([\d.]+)/i', $html, $m)) {
            return $m[1];
        }

        return null;
    }
}

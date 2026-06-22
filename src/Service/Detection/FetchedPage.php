<?php

declare(strict_types=1);

namespace App\Service\Detection;

/**
 * The homepage fetched from a monitored site, shared with every detector.
 */
final readonly class FetchedPage
{
    /**
     * @param array<string, list<string>> $headers lowercased header name => values
     */
    public function __construct(
        public string $url,
        public int $statusCode,
        public array $headers,
        public string $body,
    ) {
    }

    public function header(string $name): ?string
    {
        return $this->headers[strtolower($name)][0] ?? null;
    }
}

<?php

declare(strict_types=1);

namespace App\Service\Page;

final readonly class SitemapScanResult
{
    /**
     * @param list<array{url: string, status: int|null}> $pages
     */
    public function __construct(
        public ?string $note,
        public array $pages,
    ) {
    }
}

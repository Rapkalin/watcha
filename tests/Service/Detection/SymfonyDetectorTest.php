<?php

declare(strict_types=1);

namespace App\Tests\Service\Detection;

use App\Enum\Technology;
use App\Service\Detection\Detector\SymfonyDetector;
use App\Service\Detection\FetchedPage;
use PHPUnit\Framework\TestCase;

final class SymfonyDetectorTest extends TestCase
{
    public function testDetectsSymfonyUxImportmap(): void
    {
        $body = <<<'HTML'
            <!DOCTYPE html><html><head>
            <script type="importmap" nonce="x">{"imports":{
              "@symfony/stimulus-bundle": "/assets/@symfony/stimulus-bundle/loader-IOzBDL.js",
              "app": "/assets/app-jgPm2-L.js"
            }}</script>
            </head><body></body></html>
            HTML;

        $result = (new SymfonyDetector())->detect($this->page($body, []));

        self::assertNotNull($result);
        self::assertSame(Technology::SYMFONY, $result->technology);
        self::assertGreaterThanOrEqual(90, $result->confidence);
    }

    public function testDetectsAssetMapperFingerprint(): void
    {
        $body = '<script type="importmap">{"imports":{"app":"/assets/app-AB12cd34.js"}}</script>'
            .'<script src="/assets/vendor/es-module-shims.js"></script>'
            .'<link rel="stylesheet" href="/assets/styles/app-8qfvrDF.css">';

        $result = (new SymfonyDetector())->detect($this->page($body, []));

        self::assertNotNull($result);
        self::assertSame(Technology::SYMFONY, $result->technology);
    }

    public function testReturnsNullForNonSymfonyPage(): void
    {
        $body = '<!DOCTYPE html><html><head><title>Static site</title></head><body>Hello</body></html>';

        self::assertNull((new SymfonyDetector())->detect($this->page($body, [])));
    }

    /**
     * @param array<string, list<string>> $headers
     */
    private function page(string $body, array $headers): FetchedPage
    {
        return new FetchedPage('https://example.test', 200, $headers, $body);
    }
}

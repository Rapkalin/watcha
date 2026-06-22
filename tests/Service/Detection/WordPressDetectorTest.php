<?php

declare(strict_types=1);

namespace App\Tests\Service\Detection;

use App\Enum\Technology;
use App\Service\Detection\Detector\WordPressDetector;
use App\Service\Detection\FetchedPage;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

final class WordPressDetectorTest extends TestCase
{
    public function testVersionFromGeneratorMeta(): void
    {
        $body = '<meta name="generator" content="WordPress 6.4.2" /><link href="/wp-content/x.css">';
        $result = (new WordPressDetector(new MockHttpClient()))->detect($this->page($body));

        self::assertNotNull($result);
        self::assertSame(Technology::WORDPRESS, $result->technology);
        self::assertSame('6.4.2', $result->version);
    }

    public function testVersionFromAssetQueryString(): void
    {
        $body = '<link rel="stylesheet" href="/wp-includes/css/dist/block-library/style.min.css?ver=6.5.3">';
        $result = (new WordPressDetector(new MockHttpClient()))->detect($this->page($body));

        self::assertNotNull($result);
        self::assertSame('6.5.3', $result->version);
    }

    public function testVersionFromFeedFallback(): void
    {
        // No version in the homepage markup; the /feed/ generator carries it.
        $body = '<div class="wp-content">content</div><!-- /wp-includes/ -->';
        $client = new MockHttpClient(static function (string $method, string $url): MockResponse {
            if (str_ends_with($url, '/feed/')) {
                return new MockResponse('<rss><channel><generator>https://wordpress.org/?v=6.3.1</generator></channel></rss>');
            }

            return new MockResponse('', ['http_code' => 404]);
        });

        $result = (new WordPressDetector($client))->detect($this->page($body));

        self::assertNotNull($result);
        self::assertSame('6.3.1', $result->version);
    }

    public function testNotWordPress(): void
    {
        self::assertNull((new WordPressDetector(new MockHttpClient()))->detect($this->page('<html>plain</html>')));
    }

    private function page(string $body): FetchedPage
    {
        return new FetchedPage('https://example.test', 200, [], $body);
    }
}

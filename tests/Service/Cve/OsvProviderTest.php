<?php

declare(strict_types=1);

namespace App\Tests\Service\Cve;

use App\Enum\Severity;
use App\Enum\Technology;
use App\Service\Cve\OsvProvider;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\JsonMockResponse;

final class OsvProviderTest extends TestCase
{
    public function testSupports(): void
    {
        $provider = new OsvProvider(new MockHttpClient(), new NullLogger());

        self::assertTrue($provider->supports(Technology::SYMFONY));
        self::assertTrue($provider->supports(Technology::LARAVEL));
        self::assertTrue($provider->supports(Technology::DRUPAL));
        self::assertFalse($provider->supports(Technology::WORDPRESS));
    }

    public function testFetchMapsVulnerability(): void
    {
        $payload = [
            'vulns' => [
                [
                    'id' => 'GHSA-1234',
                    'summary' => 'Example vulnerability in Laravel',
                    'details' => 'Long details here.',
                    'aliases' => ['CVE-2024-0001', 'GHSA-1234'],
                    'database_specific' => ['severity' => 'HIGH'],
                    'published' => '2024-02-01T00:00:00Z',
                    'affected' => [[
                        'package' => ['ecosystem' => 'Packagist', 'name' => 'laravel/framework'],
                        'ranges' => [[
                            'type' => 'ECOSYSTEM',
                            'events' => [['introduced' => '11.9.0'], ['fixed' => '11.36.0']],
                        ]],
                    ]],
                    'references' => [['type' => 'ADVISORY', 'url' => 'https://example.com/adv']],
                ],
            ],
        ];

        $provider = new OsvProvider(new MockHttpClient(new JsonMockResponse($payload)), new NullLogger());

        $dtos = iterator_to_array($provider->fetch(Technology::LARAVEL));

        self::assertCount(1, $dtos);
        $dto = $dtos[0];
        self::assertSame('GHSA-1234', $dto->externalId);
        self::assertSame('CVE-2024-0001', $dto->cveId);
        self::assertSame(Severity::HIGH, $dto->severity);
        self::assertSame('>=11.9.0,<11.36.0', $dto->affectedConstraint);
        self::assertSame('11.36.0', $dto->fixedVersion);
        self::assertSame('https://example.com/adv', $dto->referenceUrl);
        self::assertNotNull($dto->publishedAt);
    }
}

<?php

declare(strict_types=1);

namespace App\Tests\Service\Version;

use App\Service\Version\VersionComparator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

final class VersionComparatorTest extends TestCase
{
    private VersionComparator $comparator;

    protected function setUp(): void
    {
        $this->comparator = new VersionComparator(new NullLogger());
    }

    #[DataProvider('affectedCases')]
    public function testIsAffected(string $version, ?string $constraint, bool $expected): void
    {
        self::assertSame($expected, $this->comparator->isAffected($version, $constraint));
    }

    /**
     * @return iterable<string, array{0: string, 1: ?string, 2: bool}>
     */
    public static function affectedCases(): iterable
    {
        yield 'in range' => ['11.20.0', '>=11.9.0,<11.36.0', true];
        yield 'below range' => ['11.5.0', '>=11.9.0,<11.36.0', false];
        yield 'patched out' => ['11.36.0', '>=11.9.0,<11.36.0', false];
        yield 'or-constraint hit' => ['12.60.0', '>=13.0.0,<13.10.0 || <12.61.1', true];
        yield 'two-part version padded' => ['6.4', '>=6.0,<6.4.10', true];
        yield 'v-prefixed' => ['v10.3.0', '>=10.0,<10.4', true];
        yield 'null constraint' => ['1.0.0', null, false];
        yield 'garbage version' => ['not-a-version', '<2.0', false];
    }

    public function testIsOutdated(): void
    {
        self::assertTrue($this->comparator->isOutdated('6.4.0', '6.4.10'));
        self::assertTrue($this->comparator->isOutdated('10.3', '11.0.0'));
        self::assertFalse($this->comparator->isOutdated('7.0.0', '7.0.0'));
        self::assertFalse($this->comparator->isOutdated('7.1.0', '7.0.0'));
    }
}

<?php

declare(strict_types=1);

namespace Tests\Unit\Invoice\Domain;

use Modules\Invoices\Domain\Exceptions\InvalidProductLine;
use Modules\Invoices\Domain\ProductLine;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ProductLineTest extends TestCase
{
    public function testCalculatesTotalUnitPrice(): void
    {
        $line = ProductLine::create('Consulting', 3, 250);

        $this->assertSame(750, $line->totalUnitPrice());
    }

    public function testAllowsZeroQuantityAndUnitPrice(): void
    {
        $line = ProductLine::create('Consulting', 0, 0);

        $this->assertSame(0, $line->totalUnitPrice());
    }

    /** @return iterable<string, array{int, int}> */
    public static function negativeValues(): iterable
    {
        yield 'negative quantity' => [-1, 100];
        yield 'negative unit price' => [1, -1];
    }

    #[DataProvider('negativeValues')]
    public function testRejectsNegativeValues(int $quantity, int $unitPrice): void
    {
        $this->expectException(InvalidProductLine::class);

        ProductLine::create('Consulting', $quantity, $unitPrice);
    }

    public function testRejectsBlankProductName(): void
    {
        $this->expectException(InvalidProductLine::class);

        ProductLine::create('   ', 1, 100);
    }

    /** @return iterable<string, array{int, int, bool}> */
    public static function sendability(): iterable
    {
        yield 'positive values' => [1, 1, true];
        yield 'zero quantity' => [0, 1, false];
        yield 'zero unit price' => [1, 0, false];
    }

    #[DataProvider('sendability')]
    public function testIsSendableOnlyWithPositiveQuantityAndUnitPrice(int $quantity, int $unitPrice, bool $expected): void
    {
        $line = ProductLine::create('Consulting', $quantity, $unitPrice);

        $this->assertSame($expected, $line->isSendable());
    }
}

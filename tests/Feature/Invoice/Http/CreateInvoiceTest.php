<?php

declare(strict_types=1);

namespace Tests\Feature\Invoice\Http;

use PHPUnit\Framework\Attributes\DataProvider;
use Ramsey\Uuid\Uuid;
use Tests\TestCase;

final class CreateInvoiceTest extends TestCase
{
    private const array VALID_PAYLOAD = [
        'customer_name' => 'John Doe',
        'customer_email' => 'john@example.com',
        'product_lines' => [
            ['product_name' => 'Consulting', 'quantity' => 2, 'unit_price' => 1500],
            ['product_name' => 'Hosting', 'quantity' => 3, 'unit_price' => 200],
        ],
    ];

    public function testCreatesDraftInvoiceWithCalculatedTotals(): void
    {
        $response = $this->postJson(route('invoices.store'), self::VALID_PAYLOAD);

        $response->assertCreated()
            ->assertJsonPath('data.status', 'draft')
            ->assertJsonPath('data.customer_name', 'John Doe')
            ->assertJsonPath('data.customer_email', 'john@example.com')
            ->assertJsonPath('data.product_lines.0.product_name', 'Consulting')
            ->assertJsonPath('data.product_lines.0.quantity', 2)
            ->assertJsonPath('data.product_lines.0.unit_price', 1500)
            ->assertJsonPath('data.product_lines.0.total_unit_price', 3000)
            ->assertJsonPath('data.product_lines.1.total_unit_price', 600)
            ->assertJsonPath('data.total_price', 3600);
        $this->assertTrue(Uuid::isValid($response->json('data.id')));
    }

    public function testCreatesInvoiceWithoutProductLines(): void
    {
        $payload = self::VALID_PAYLOAD;
        unset($payload['product_lines']);

        $this->postJson(route('invoices.store'), $payload)
            ->assertCreated()
            ->assertJsonPath('data.product_lines', [])
            ->assertJsonPath('data.total_price', 0);
    }

    public function testAllowsZeroQuantityAndUnitPriceInDraft(): void
    {
        $payload = array_replace(self::VALID_PAYLOAD, [
            'product_lines' => [['product_name' => 'Consulting', 'quantity' => 0, 'unit_price' => 0]],
        ]);

        $this->postJson(route('invoices.store'), $payload)
            ->assertCreated()
            ->assertJsonPath('data.product_lines.0.total_unit_price', 0);
    }

    public function testIgnoresStatusProvidedByClient(): void
    {
        $payload = array_replace(self::VALID_PAYLOAD, ['status' => 'sent-to-client']);

        $this->postJson(route('invoices.store'), $payload)
            ->assertCreated()
            ->assertJsonPath('data.status', 'draft');
    }

    /** @return iterable<string, array{array<string, mixed>, string}> */
    public static function invalidPayloads(): iterable
    {
        yield 'missing customer name' => [['customer_name' => null], 'customer_name'];
        yield 'invalid customer email' => [['customer_email' => 'not-an-email'], 'customer_email'];
        yield 'missing product name' => [
            ['product_lines' => [['quantity' => 1, 'unit_price' => 100]]],
            'product_lines.0.product_name',
        ];
        yield 'negative quantity' => [
            ['product_lines' => [['product_name' => 'Consulting', 'quantity' => -1, 'unit_price' => 100]]],
            'product_lines.0.quantity',
        ];
        yield 'negative unit price' => [
            ['product_lines' => [['product_name' => 'Consulting', 'quantity' => 1, 'unit_price' => -1]]],
            'product_lines.0.unit_price',
        ];
        yield 'fractional quantity' => [
            ['product_lines' => [['product_name' => 'Consulting', 'quantity' => 1.5, 'unit_price' => 100]]],
            'product_lines.0.quantity',
        ];
        yield 'quantity above limit' => [
            ['product_lines' => [['product_name' => 'Consulting', 'quantity' => 1000001, 'unit_price' => 100]]],
            'product_lines.0.quantity',
        ];
        yield 'too many product lines' => [
            ['product_lines' => array_fill(0, 101, ['product_name' => 'Consulting', 'quantity' => 1, 'unit_price' => 100])],
            'product_lines',
        ];
    }

    /** @param array<string, mixed> $override */
    #[DataProvider('invalidPayloads')]
    public function testRejectsInvalidPayload(array $override, string $invalidField): void
    {
        $this->postJson(route('invoices.store'), array_replace(self::VALID_PAYLOAD, $override))
            ->assertUnprocessable()
            ->assertJsonValidationErrors($invalidField);

        $this->assertDatabaseCount('invoices', 0);
    }
}

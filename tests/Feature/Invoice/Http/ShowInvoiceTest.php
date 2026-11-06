<?php

declare(strict_types=1);

namespace Tests\Feature\Invoice\Http;

use Ramsey\Uuid\Uuid;
use Tests\TestCase;

final class ShowInvoiceTest extends TestCase
{
    use CreatesInvoices;

    public function testReturnsInvoice(): void
    {
        $id = $this->createInvoice();

        $this->getJson(route('invoices.show', ['id' => $id]))
            ->assertOk()
            ->assertExactJson([
                'data' => [
                    'id' => $id,
                    'status' => 'draft',
                    'customer_name' => 'John Doe',
                    'customer_email' => 'john@example.com',
                    'product_lines' => [
                        [
                            'product_name' => 'Consulting',
                            'quantity' => 2,
                            'unit_price' => 1500,
                            'total_unit_price' => 3000,
                        ],
                    ],
                    'total_price' => 3000,
                ],
            ]);
    }

    public function testReturnsNotFoundForUnknownInvoice(): void
    {
        $id = Uuid::uuid4()->toString();

        $this->getJson(route('invoices.show', ['id' => $id]))
            ->assertNotFound()
            ->assertJsonPath('message', "Invoice {$id} not found.");
    }

    public function testReturnsNotFoundForMalformedId(): void
    {
        $this->getJson('/api/invoices/not-a-uuid')->assertNotFound();
    }
}

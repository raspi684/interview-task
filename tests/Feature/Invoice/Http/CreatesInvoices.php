<?php

declare(strict_types=1);

namespace Tests\Feature\Invoice\Http;

trait CreatesInvoices
{
    /** @param list<array{product_name: string, quantity: int, unit_price: int}> $productLines */
    private function createInvoice(
        array $productLines = [['product_name' => 'Consulting', 'quantity' => 2, 'unit_price' => 1500]],
    ): string {
        return $this->postJson(route('invoices.store'), [
            'customer_name' => 'John Doe',
            'customer_email' => 'john@example.com',
            'product_lines' => $productLines,
        ])->assertCreated()->json('data.id');
    }
}

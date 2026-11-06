<?php

declare(strict_types=1);

namespace Modules\Invoices\Presentation\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Modules\Invoices\Application\Data\CreateInvoiceData;
use Modules\Invoices\Application\Data\ProductLineData;

final class CreateInvoiceRequest extends FormRequest
{
    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return [
            'customer_name' => ['required', 'string', 'max:255'],
            'customer_email' => ['required', 'string', 'email', 'max:255'],
            'product_lines' => ['sometimes', 'array', 'max:100'],
            'product_lines.*.product_name' => ['required', 'string', 'max:255'],
            // Per-field bounds fit a 32-bit DB integer column; 100 lines × max line total (1e15) stays far below PHP_INT_MAX.
            'product_lines.*.quantity' => ['required', 'integer', 'min:0', 'max:1000000'],
            'product_lines.*.unit_price' => ['required', 'integer', 'min:0', 'max:1000000000'],
        ];
    }

    public function toData(): CreateInvoiceData
    {
        /** @var list<array{product_name: string, quantity: int|string, unit_price: int|string}> $lines */
        $lines = array_values($this->validated('product_lines', []));

        return new CreateInvoiceData(
            customerName: $this->string('customer_name')->toString(),
            customerEmail: $this->string('customer_email')->toString(),
            productLines: array_map(
                static fn (array $line): ProductLineData => new ProductLineData(
                    productName: $line['product_name'],
                    quantity: (int) $line['quantity'],
                    unitPrice: (int) $line['unit_price'],
                ),
                $lines,
            ),
        );
    }
}

<?php

declare(strict_types=1);

namespace Modules\Invoices\Presentation\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Invoices\Application\Data\InvoiceView;
use Modules\Invoices\Application\Data\ProductLineView;

/** @property InvoiceView $resource */
final class InvoiceResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $invoice = $this->resource;

        return [
            'id' => $invoice->id,
            'status' => $invoice->status,
            'customer_name' => $invoice->customerName,
            'customer_email' => $invoice->customerEmail,
            'product_lines' => array_map(
                static fn (ProductLineView $line): array => [
                    'product_name' => $line->productName,
                    'quantity' => $line->quantity,
                    'unit_price' => $line->unitPrice,
                    'total_unit_price' => $line->totalUnitPrice,
                ],
                $invoice->productLines,
            ),
            'total_price' => $invoice->totalPrice,
        ];
    }
}

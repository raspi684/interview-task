<?php

declare(strict_types=1);

namespace Modules\Invoices\Application\Actions;

use Illuminate\Database\ConnectionInterface;
use Modules\Invoices\Application\Data\CreateInvoiceData;
use Modules\Invoices\Application\Data\ProductLineData;
use Modules\Invoices\Domain\Invoice;
use Modules\Invoices\Domain\InvoiceRepositoryInterface;
use Modules\Invoices\Domain\ProductLine;

final readonly class CreateInvoice
{
    public function __construct(
        private ConnectionInterface $connection,
        private InvoiceRepositoryInterface $invoices,
    ) {}

    public function handle(CreateInvoiceData $data): Invoice
    {
        $invoice = Invoice::create(
            customerName: $data->customerName,
            customerEmail: $data->customerEmail,
            productLines: array_map(
                static fn (ProductLineData $line): ProductLine => ProductLine::create(
                    productName: $line->productName,
                    quantity: $line->quantity,
                    unitPrice: $line->unitPrice,
                ),
                $data->productLines,
            ),
        );

        $this->connection->transaction(fn () => $this->invoices->save($invoice));

        return $invoice;
    }
}

<?php

declare(strict_types=1);

namespace Modules\Invoices\Application\Actions;

use Modules\Invoices\Application\Data\CreateInvoiceData;
use Modules\Invoices\Application\Data\InvoiceView;
use Modules\Invoices\Application\Data\ProductLineData;
use Modules\Invoices\Application\Ports\TransactionManagerInterface;
use Modules\Invoices\Domain\Customer;
use Modules\Invoices\Domain\Invoice;
use Modules\Invoices\Domain\InvoiceRepositoryInterface;
use Modules\Invoices\Domain\ProductLine;

final readonly class CreateInvoice
{
    public function __construct(
        private TransactionManagerInterface $transactions,
        private InvoiceRepositoryInterface $invoices,
    ) {}

    public function handle(CreateInvoiceData $data): InvoiceView
    {
        $invoice = Invoice::create(
            customer: new Customer($data->customerName, $data->customerEmail),
            productLines: array_map(
                static fn (ProductLineData $line): ProductLine => ProductLine::create(
                    productName: $line->productName,
                    quantity: $line->quantity,
                    unitPrice: $line->unitPrice,
                ),
                $data->productLines,
            ),
        );

        $this->transactions->run(fn () => $this->invoices->save($invoice));

        return InvoiceView::fromInvoice($invoice);
    }
}

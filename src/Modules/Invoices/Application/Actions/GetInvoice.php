<?php

declare(strict_types=1);

namespace Modules\Invoices\Application\Actions;

use Modules\Invoices\Domain\Exceptions\InvoiceNotFound;
use Modules\Invoices\Domain\Invoice;
use Modules\Invoices\Domain\InvoiceRepositoryInterface;
use Ramsey\Uuid\UuidInterface;

final readonly class GetInvoice
{
    public function __construct(
        private InvoiceRepositoryInterface $invoices,
    ) {}

    public function handle(UuidInterface $id): Invoice
    {
        return $this->invoices->find($id) ?? throw InvoiceNotFound::withId($id);
    }
}

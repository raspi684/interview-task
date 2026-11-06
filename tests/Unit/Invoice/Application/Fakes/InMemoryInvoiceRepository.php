<?php

declare(strict_types=1);

namespace Tests\Unit\Invoice\Application\Fakes;

use Modules\Invoices\Domain\Invoice;
use Modules\Invoices\Domain\InvoiceRepositoryInterface;
use Ramsey\Uuid\UuidInterface;

final class InMemoryInvoiceRepository implements InvoiceRepositoryInterface
{
    /** @var array<string, Invoice> */
    private array $invoices = [];

    public function find(UuidInterface $id): ?Invoice
    {
        // Clones mimic a real store: changes are visible only after save().
        $invoice = $this->invoices[$id->toString()] ?? null;

        return $invoice === null ? null : clone $invoice;
    }

    public function findForUpdate(UuidInterface $id): ?Invoice
    {
        return $this->find($id);
    }

    public function save(Invoice $invoice): void
    {
        $this->invoices[$invoice->id()->toString()] = clone $invoice;
    }
}

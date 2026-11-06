<?php

declare(strict_types=1);

namespace Modules\Invoices\Domain;

use Ramsey\Uuid\UuidInterface;

interface InvoiceRepositoryInterface
{
    public function find(UuidInterface $id): ?Invoice;

    /** Locks the invoice row until the surrounding transaction ends. */
    public function findForUpdate(UuidInterface $id): ?Invoice;

    public function save(Invoice $invoice): void;
}

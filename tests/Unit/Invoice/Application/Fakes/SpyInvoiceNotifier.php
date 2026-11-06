<?php

declare(strict_types=1);

namespace Tests\Unit\Invoice\Application\Fakes;

use Modules\Invoices\Application\Ports\InvoiceNotifierInterface;
use Modules\Invoices\Domain\Invoice;
use Ramsey\Uuid\UuidInterface;
use Throwable;

final class SpyInvoiceNotifier implements InvoiceNotifierInterface
{
    /** @var list<UuidInterface> */
    public array $notifiedInvoiceIds = [];

    public ?Throwable $failWith = null;

    public function notifyInvoiceSent(Invoice $invoice): void
    {
        if ($this->failWith !== null) {
            throw $this->failWith;
        }

        $this->notifiedInvoiceIds[] = $invoice->id();
    }
}

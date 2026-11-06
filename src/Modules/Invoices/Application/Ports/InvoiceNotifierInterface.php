<?php

declare(strict_types=1);

namespace Modules\Invoices\Application\Ports;

use Modules\Invoices\Domain\Invoice;

interface InvoiceNotifierInterface
{
    /** Hands the e-mail over to the notification provider; delivery is confirmed asynchronously. */
    public function notifyInvoiceSent(Invoice $invoice): void;
}

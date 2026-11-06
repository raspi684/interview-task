<?php

declare(strict_types=1);

namespace Modules\Invoices\Infrastructure\Notifications;

use Modules\Invoices\Application\Ports\InvoiceNotifierInterface;
use Modules\Invoices\Domain\Invoice;
use Modules\Notifications\Api\Data\NotifyData;
use Modules\Notifications\Api\NotificationFacadeInterface;

final readonly class NotificationFacadeInvoiceNotifier implements InvoiceNotifierInterface
{
    public function __construct(
        private NotificationFacadeInterface $notifications,
    ) {}

    public function notifyInvoiceSent(Invoice $invoice): void
    {
        $this->notifications->notify(new NotifyData(
            resourceId: $invoice->id(),
            toEmail: $invoice->customer()->email,
            subject: sprintf('Invoice %s', $invoice->id()->toString()),
            message: sprintf(
                "Dear %s,\n\nyour invoice %s has been issued. Total amount due: %d.",
                $invoice->customer()->name,
                $invoice->id()->toString(),
                $invoice->totalPrice(),
            ),
        ));
    }
}

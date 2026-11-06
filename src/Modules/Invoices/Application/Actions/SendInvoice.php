<?php

declare(strict_types=1);

namespace Modules\Invoices\Application\Actions;

use Exception;
use Illuminate\Database\ConnectionInterface;
use Modules\Invoices\Application\Exceptions\InvoiceNotificationFailed;
use Modules\Invoices\Application\Ports\InvoiceNotifierInterface;
use Modules\Invoices\Domain\Exceptions\InvoiceNotFound;
use Modules\Invoices\Domain\Invoice;
use Modules\Invoices\Domain\InvoiceRepositoryInterface;
use Ramsey\Uuid\UuidInterface;

final readonly class SendInvoice
{
    public function __construct(
        private ConnectionInterface $connection,
        private InvoiceRepositoryInterface $invoices,
        private InvoiceNotifierInterface $notifier,
    ) {}

    /**
     * The notification is requested inside the transaction: if the provider rejects it,
     * the status change rolls back and the invoice stays in draft, ready to be sent again.
     * A delivery webhook racing this transaction waits on the row lock and sees "sending".
     */
    public function handle(UuidInterface $id): Invoice
    {
        return $this->connection->transaction(function () use ($id): Invoice {
            $invoice = $this->invoices->findForUpdate($id) ?? throw InvoiceNotFound::withId($id);

            $invoice->send();
            $this->invoices->save($invoice);

            try {
                $this->notifier->notifyInvoiceSent($invoice);
            } catch (Exception $exception) {
                throw InvoiceNotificationFailed::forInvoice($id, $exception);
            }

            return $invoice;
        });
    }
}

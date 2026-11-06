<?php

declare(strict_types=1);

namespace Modules\Invoices\Application\Actions;

use Modules\Invoices\Application\Ports\TransactionManagerInterface;
use Modules\Invoices\Domain\Enums\DeliveryConfirmation;
use Modules\Invoices\Domain\InvoiceRepositoryInterface;
use Psr\Log\LoggerInterface;
use Ramsey\Uuid\UuidInterface;

/**
 * Delivery webhooks are not addressed to invoices only, so an unknown resource or an invoice
 * that is not awaiting delivery is logged and ignored instead of failing the webhook.
 */
final readonly class MarkInvoiceAsSentToClient
{
    public function __construct(
        private TransactionManagerInterface $transactions,
        private InvoiceRepositoryInterface $invoices,
        private LoggerInterface $logger,
    ) {}

    public function handle(UuidInterface $invoiceId): void
    {
        $this->transactions->run(function () use ($invoiceId): void {
            $context = ['invoice_id' => $invoiceId->toString()];
            $invoice = $this->invoices->findForUpdate($invoiceId);

            if ($invoice === null) {
                $this->logger->info('Delivery confirmation ignored: resource is not an invoice.', $context);

                return;
            }

            match ($invoice->confirmDelivery()) {
                DeliveryConfirmation::Confirmed => $this->invoices->save($invoice),
                DeliveryConfirmation::AlreadyConfirmed => $this->logger->info(
                    'Delivery confirmation ignored: invoice already sent to client.',
                    $context,
                ),
                DeliveryConfirmation::NotAwaitingDelivery => $this->logger->warning(
                    sprintf('Delivery confirmation ignored: invoice is in "%s" status.', $invoice->status()->value),
                    $context,
                ),
            };
        });
    }
}

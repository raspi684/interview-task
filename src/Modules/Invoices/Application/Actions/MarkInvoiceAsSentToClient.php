<?php

declare(strict_types=1);

namespace Modules\Invoices\Application\Actions;

use Illuminate\Database\ConnectionInterface;
use Modules\Invoices\Domain\Enums\StatusEnum;
use Modules\Invoices\Domain\Exceptions\InvalidStatusTransition;
use Modules\Invoices\Domain\InvoiceRepositoryInterface;
use Psr\Log\LoggerInterface;
use Ramsey\Uuid\UuidInterface;

/**
 * Delivery webhooks are at-least-once and not addressed to invoices only, so anything other
 * than a "sending" invoice is logged and ignored instead of failing the webhook.
 */
final readonly class MarkInvoiceAsSentToClient
{
    public function __construct(
        private ConnectionInterface $connection,
        private InvoiceRepositoryInterface $invoices,
        private LoggerInterface $logger,
    ) {}

    public function handle(UuidInterface $invoiceId): void
    {
        $this->connection->transaction(function () use ($invoiceId): void {
            $context = ['invoice_id' => $invoiceId->toString()];
            $invoice = $this->invoices->findForUpdate($invoiceId);

            if ($invoice === null) {
                $this->logger->info('Delivery confirmation ignored: resource is not an invoice.', $context);

                return;
            }

            if ($invoice->status() === StatusEnum::SentToClient) {
                $this->logger->info('Delivery confirmation ignored: invoice already sent to client.', $context);

                return;
            }

            try {
                $invoice->markAsSentToClient();
            } catch (InvalidStatusTransition $exception) {
                $this->logger->warning('Delivery confirmation ignored: '.$exception->getMessage(), $context);

                return;
            }

            $this->invoices->save($invoice);
        });
    }
}

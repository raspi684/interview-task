<?php

declare(strict_types=1);

namespace Modules\Invoices\Application\Listeners;

use Modules\Invoices\Application\Actions\MarkInvoiceAsSentToClient;
use Modules\Notifications\Api\Events\WebhookDeliveredEvent;

final readonly class MarkInvoiceAsSentToClientOnDelivery
{
    public function __construct(
        private MarkInvoiceAsSentToClient $markInvoiceAsSentToClient,
    ) {}

    public function handle(WebhookDeliveredEvent $event): void
    {
        $this->markInvoiceAsSentToClient->handle($event->resourceId);
    }
}

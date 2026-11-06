<?php

declare(strict_types=1);

namespace Modules\Invoices\Infrastructure\Providers;

use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Support\ServiceProvider;
use Modules\Invoices\Application\Listeners\MarkInvoiceAsSentToClientOnDelivery;
use Modules\Invoices\Application\Ports\InvoiceNotifierInterface;
use Modules\Invoices\Domain\InvoiceRepositoryInterface;
use Modules\Invoices\Infrastructure\Notifications\NotificationFacadeInvoiceNotifier;
use Modules\Invoices\Infrastructure\Persistence\DatabaseInvoiceRepository;
use Modules\Notifications\Api\Events\WebhookDeliveredEvent;

final class InvoiceServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->scoped(InvoiceRepositoryInterface::class, DatabaseInvoiceRepository::class);
        $this->app->scoped(InvoiceNotifierInterface::class, NotificationFacadeInvoiceNotifier::class);
    }

    public function boot(Dispatcher $events): void
    {
        $events->listen(WebhookDeliveredEvent::class, MarkInvoiceAsSentToClientOnDelivery::class);
    }
}

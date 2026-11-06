<?php

declare(strict_types=1);

namespace Modules\Invoices;

use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Foundation\Exceptions\Handler;
use Illuminate\Support\ServiceProvider;
use Modules\Invoices\Application\Listeners\MarkInvoiceAsSentToClientOnDelivery;
use Modules\Invoices\Application\Ports\InvoiceNotifierInterface;
use Modules\Invoices\Application\Ports\TransactionManagerInterface;
use Modules\Invoices\Domain\InvoiceRepositoryInterface;
use Modules\Invoices\Infrastructure\Notifications\NotificationFacadeInvoiceNotifier;
use Modules\Invoices\Infrastructure\Persistence\DatabaseInvoiceRepository;
use Modules\Invoices\Infrastructure\Persistence\DatabaseTransactionManager;
use Modules\Invoices\Presentation\Http\InvoiceExceptionRenderer;
use Modules\Notifications\Api\Events\WebhookDeliveredEvent;

final class InvoiceServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->scoped(InvoiceRepositoryInterface::class, DatabaseInvoiceRepository::class);
        $this->app->scoped(InvoiceNotifierInterface::class, NotificationFacadeInvoiceNotifier::class);
        $this->app->scoped(TransactionManagerInterface::class, DatabaseTransactionManager::class);

        // Same hook Laravel uses for bootstrap/app.php withExceptions(); it also fires when the handler
        // is wrapped by a decorator (e.g. Collision in the console), unlike the ExceptionHandler contract.
        $this->app->afterResolving(Handler::class, InvoiceExceptionRenderer::register(...));
    }

    public function boot(Dispatcher $events): void
    {
        $events->listen(WebhookDeliveredEvent::class, MarkInvoiceAsSentToClientOnDelivery::class);
    }
}

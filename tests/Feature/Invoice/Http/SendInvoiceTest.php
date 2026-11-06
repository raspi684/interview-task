<?php

declare(strict_types=1);

namespace Tests\Feature\Invoice\Http;

use Modules\Notifications\Infrastructure\Drivers\DriverInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use Ramsey\Uuid\Uuid;
use RuntimeException;
use Tests\TestCase;

/**
 * The driver double must be bound before the first request: the route caches the controller
 * together with its (scoped) notification facade.
 */
final class SendInvoiceTest extends TestCase
{
    use CreatesInvoices;

    public function testSendsDraftInvoiceAndRequestsEmailNotification(): void
    {
        $driver = $this->createMock(DriverInterface::class);
        $this->app->instance(DriverInterface::class, $driver);
        $id = $this->createInvoice();

        $driver->expects($this->once())
            ->method('send')
            ->with('john@example.com', $this->stringContains($id), $this->isString(), $id);

        $this->postJson(route('invoices.send', ['id' => $id]))
            ->assertAccepted()
            ->assertJsonPath('data.status', 'sending');

        $this->getJson(route('invoices.show', ['id' => $id]))->assertJsonPath('data.status', 'sending');
    }

    public function testRejectsSendingInvoiceThatIsAlreadySending(): void
    {
        $driver = $this->createMock(DriverInterface::class);
        $driver->expects($this->once())->method('send');
        $this->app->instance(DriverInterface::class, $driver);
        $id = $this->createInvoice();

        $this->postJson(route('invoices.send', ['id' => $id]))->assertAccepted();
        $this->postJson(route('invoices.send', ['id' => $id]))
            ->assertStatus(409)
            ->assertJsonPath('message', 'Invoice status cannot change from "sending" to "sending".');
    }

    public function testRejectsSendingInvoiceWithoutProductLines(): void
    {
        $id = $this->createInvoice([]);

        $this->postJson(route('invoices.send', ['id' => $id]))->assertUnprocessable();

        $this->getJson(route('invoices.show', ['id' => $id]))->assertJsonPath('data.status', 'draft');
    }

    /** @return iterable<string, array{int, int}> */
    public static function nonPositiveLineValues(): iterable
    {
        yield 'zero quantity' => [0, 100];
        yield 'zero unit price' => [1, 0];
    }

    #[DataProvider('nonPositiveLineValues')]
    public function testRejectsSendingInvoiceWithNonPositiveProductLine(int $quantity, int $unitPrice): void
    {
        $id = $this->createInvoice([['product_name' => 'Consulting', 'quantity' => $quantity, 'unit_price' => $unitPrice]]);

        $this->postJson(route('invoices.send', ['id' => $id]))->assertUnprocessable();

        $this->getJson(route('invoices.show', ['id' => $id]))->assertJsonPath('data.status', 'draft');
    }

    public function testReturnsNotFoundForUnknownInvoice(): void
    {
        $this->postJson(route('invoices.send', ['id' => Uuid::uuid4()->toString()]))->assertNotFound();
    }

    public function testKeepsInvoiceInDraftWhenNotificationCannotBeRequested(): void
    {
        $calls = 0;
        $driver = $this->createMock(DriverInterface::class);
        $driver->expects($this->exactly(2))->method('send')->willReturnCallback(
            static function () use (&$calls): void {
                if ($calls++ === 0) {
                    throw new RuntimeException('Provider unavailable');
                }
            },
        );
        $this->app->instance(DriverInterface::class, $driver);
        $id = $this->createInvoice();

        $this->postJson(route('invoices.send', ['id' => $id]))->assertStatus(503);
        $this->getJson(route('invoices.show', ['id' => $id]))->assertJsonPath('data.status', 'draft');

        $this->postJson(route('invoices.send', ['id' => $id]))->assertAccepted();
        $this->getJson(route('invoices.show', ['id' => $id]))->assertJsonPath('data.status', 'sending');
    }
}

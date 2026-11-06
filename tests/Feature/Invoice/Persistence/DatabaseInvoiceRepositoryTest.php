<?php

declare(strict_types=1);

namespace Tests\Feature\Invoice\Persistence;

use Illuminate\Database\ConnectionInterface;
use Modules\Invoices\Domain\Customer;
use Modules\Invoices\Domain\Enums\StatusEnum;
use Modules\Invoices\Domain\Invoice;
use Modules\Invoices\Domain\InvoiceRepositoryInterface;
use Modules\Invoices\Domain\ProductLine;
use Ramsey\Uuid\Uuid;
use Tests\TestCase;
use UnexpectedValueException;

final class DatabaseInvoiceRepositoryTest extends TestCase
{
    private InvoiceRepositoryInterface $invoices;

    protected function setUp(): void
    {
        parent::setUp();

        $this->invoices = $this->app->make(InvoiceRepositoryInterface::class);
    }

    public function testRoundTripsInvoiceWithProductLinesInOrder(): void
    {
        $invoice = Invoice::create(new Customer('John Doe', 'john@example.com'), [
            ProductLine::create('Consulting', 2, 1500),
            ProductLine::create('Hosting', 3, 200),
            ProductLine::create('Support', 0, 0),
        ]);

        $this->invoices->save($invoice);
        $found = $this->invoices->find($invoice->id());

        $this->assertNotNull($found);
        $this->assertTrue($invoice->id()->equals($found->id()));
        $this->assertSame(StatusEnum::Draft, $found->status());
        $this->assertSame('John Doe', $found->customer()->name);
        $this->assertSame('john@example.com', $found->customer()->email);
        $this->assertSame(
            ['Consulting', 'Hosting', 'Support'],
            array_map(static fn (ProductLine $line): string => $line->productName, $found->productLines()),
        );
        $this->assertSame(3600, $found->totalPrice());
    }

    public function testReturnsNullForUnknownInvoice(): void
    {
        $this->assertNull($this->invoices->find(Uuid::uuid4()));
    }

    public function testPersistsStatusChangeWithoutDuplicatingProductLines(): void
    {
        $invoice = Invoice::create(new Customer('John Doe', 'john@example.com'), [ProductLine::create('Consulting', 1, 100)]);
        $this->invoices->save($invoice);

        $loaded = $this->invoices->findForUpdate($invoice->id());
        $this->assertNotNull($loaded);
        $loaded->requestSending();
        $this->invoices->save($loaded);

        $this->assertSame(StatusEnum::Sending, $this->invoices->find($invoice->id())?->status());
        $this->assertDatabaseCount('invoices', 1);
        $this->assertDatabaseCount('invoice_product_lines', 1);
    }

    public function testRejectsStoredInvoiceThatViolatesDomainRules(): void
    {
        $id = Uuid::uuid4()->toString();
        $this->app->make(ConnectionInterface::class)->table('invoices')->insert([
            'id' => $id,
            'customer_name' => 'John Doe',
            'customer_email' => 'not-an-email',
            'status' => StatusEnum::Draft->value,
        ]);

        // Corrupt stored data is a server-side problem, not a domain error the client could fix.
        $this->expectException(UnexpectedValueException::class);

        $this->invoices->find(Uuid::fromString($id));
    }
}

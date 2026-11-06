<?php

declare(strict_types=1);

namespace Tests\Unit\Invoice\Application;

use Modules\Invoices\Application\Actions\MarkInvoiceAsSentToClient;
use Modules\Invoices\Domain\Enums\StatusEnum;
use Modules\Invoices\Domain\Invoice;
use Modules\Invoices\Domain\InvoiceRepositoryInterface;
use Modules\Invoices\Domain\ProductLine;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Ramsey\Uuid\Uuid;
use RuntimeException;
use Tests\Unit\Invoice\Application\Fakes\InlineTransactions;
use Tests\Unit\Invoice\Application\Fakes\InMemoryInvoiceRepository;

final class MarkInvoiceAsSentToClientTest extends TestCase
{
    use InlineTransactions;

    private InMemoryInvoiceRepository $invoices;

    private LoggerInterface&MockObject $logger;

    private MarkInvoiceAsSentToClient $markInvoiceAsSentToClient;

    protected function setUp(): void
    {
        $this->invoices = new InMemoryInvoiceRepository;
        $this->logger = $this->createMock(LoggerInterface::class);
        $this->markInvoiceAsSentToClient = new MarkInvoiceAsSentToClient(
            connection: $this->inlineTransactions(),
            invoices: $this->invoices,
            logger: $this->logger,
        );
    }

    public function testMarksSendingInvoiceAsSentToClient(): void
    {
        $invoice = $this->storedInvoiceIn(StatusEnum::Sending);

        $this->markInvoiceAsSentToClient->handle($invoice->id());

        $this->assertSame(StatusEnum::SentToClient, $this->invoices->find($invoice->id())?->status());
    }

    public function testIgnoresRepeatedDeliveryConfirmation(): void
    {
        $invoice = $this->storedInvoiceIn(StatusEnum::SentToClient);
        $this->logger->expects($this->once())->method('info');
        $this->logger->expects($this->never())->method('warning');

        $this->markInvoiceAsSentToClient->handle($invoice->id());

        $this->assertSame(StatusEnum::SentToClient, $this->invoices->find($invoice->id())?->status());
    }

    public function testIgnoresDeliveryConfirmationForDraftInvoice(): void
    {
        $invoice = $this->storedInvoiceIn(StatusEnum::Draft);
        $this->logger->expects($this->once())->method('warning');

        $this->markInvoiceAsSentToClient->handle($invoice->id());

        $this->assertSame(StatusEnum::Draft, $this->invoices->find($invoice->id())?->status());
    }

    public function testIgnoresDeliveryConfirmationForUnknownResource(): void
    {
        $this->logger->expects($this->once())->method('info');

        $this->markInvoiceAsSentToClient->handle(Uuid::uuid4());
    }

    public function testPropagatesInfrastructureFailure(): void
    {
        $repository = $this->createStub(InvoiceRepositoryInterface::class);
        $repository->method('findForUpdate')->willThrowException(new RuntimeException('Database unavailable'));
        $action = new MarkInvoiceAsSentToClient(
            connection: $this->inlineTransactions(),
            invoices: $repository,
            logger: $this->logger,
        );

        $this->expectException(RuntimeException::class);

        $action->handle(Uuid::uuid4());
    }

    private function storedInvoiceIn(StatusEnum $status): Invoice
    {
        $invoice = Invoice::reconstitute(
            id: Uuid::uuid4(),
            status: $status,
            customerName: 'John Doe',
            customerEmail: 'john@example.com',
            productLines: [ProductLine::create('Consulting', 1, 100)],
        );
        $this->invoices->save($invoice);

        return $invoice;
    }
}

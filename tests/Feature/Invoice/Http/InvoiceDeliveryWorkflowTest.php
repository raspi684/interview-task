<?php

declare(strict_types=1);

namespace Tests\Feature\Invoice\Http;

use Illuminate\Testing\TestResponse;
use Ramsey\Uuid\Uuid;
use Tests\TestCase;

final class InvoiceDeliveryWorkflowTest extends TestCase
{
    use CreatesInvoices;

    public function testDeliveredInvoiceIsMarkedAsSentToClient(): void
    {
        $id = $this->createInvoice();
        $this->postJson(route('invoices.send', ['id' => $id]))->assertAccepted();

        $this->deliver($id)->assertNoContent();

        $this->getJson(route('invoices.show', ['id' => $id]))->assertJsonPath('data.status', 'sent-to-client');
    }

    public function testRepeatedDeliveryWebhookIsIgnored(): void
    {
        $id = $this->createInvoice();
        $this->postJson(route('invoices.send', ['id' => $id]))->assertAccepted();

        $this->deliver($id)->assertNoContent();
        $this->deliver($id)->assertNoContent();

        $this->getJson(route('invoices.show', ['id' => $id]))->assertJsonPath('data.status', 'sent-to-client');
    }

    public function testDeliveryWebhookForDraftInvoiceDoesNotChangeStatus(): void
    {
        $id = $this->createInvoice();

        $this->deliver($id)->assertNoContent();

        $this->getJson(route('invoices.show', ['id' => $id]))->assertJsonPath('data.status', 'draft');
    }

    public function testDeliveryWebhookForUnknownResourceIsAccepted(): void
    {
        $this->deliver(Uuid::uuid4()->toString())->assertNoContent();
    }

    private function deliver(string $reference): TestResponse
    {
        return $this->getJson(route('notification.hook', ['action' => 'delivered', 'reference' => $reference]));
    }
}

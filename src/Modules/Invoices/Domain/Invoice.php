<?php

declare(strict_types=1);

namespace Modules\Invoices\Domain;

use InvalidArgumentException;
use Modules\Invoices\Domain\Enums\DeliveryConfirmation;
use Modules\Invoices\Domain\Enums\StatusEnum;
use Modules\Invoices\Domain\Exceptions\InvalidStatusTransition;
use Modules\Invoices\Domain\Exceptions\InvoiceCannotBeSent;
use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;

final class Invoice
{
    /** @var list<ProductLine> */
    private readonly array $productLines;

    /** @param array<ProductLine> $productLines */
    private function __construct(
        private readonly UuidInterface $id,
        private StatusEnum $status,
        private readonly Customer $customer,
        array $productLines,
    ) {
        foreach ($productLines as $line) {
            if (! $line instanceof ProductLine) {
                throw new InvalidArgumentException('Invoice product lines must be ProductLine instances.');
            }
        }

        $this->productLines = array_values($productLines);
    }

    /** @param list<ProductLine> $productLines */
    public static function create(Customer $customer, array $productLines): self
    {
        return new self(Uuid::uuid4(), StatusEnum::Draft, $customer, $productLines);
    }

    /** @param list<ProductLine> $productLines */
    public static function reconstitute(
        UuidInterface $id,
        StatusEnum $status,
        Customer $customer,
        array $productLines,
    ): self {
        return new self($id, $status, $customer, $productLines);
    }

    /** Moves the invoice to "sending"; the e-mail itself is handed over by the application layer. */
    public function requestSending(): void
    {
        if ($this->status !== StatusEnum::Draft) {
            throw InvalidStatusTransition::between($this->status, StatusEnum::Sending);
        }

        if ($this->productLines === []) {
            throw InvoiceCannotBeSent::withoutProductLines();
        }

        foreach ($this->productLines as $line) {
            if (! $line->isSendable()) {
                throw InvoiceCannotBeSent::withNonPositiveProductLine($line->productName);
            }
        }

        $this->status = StatusEnum::Sending;
    }

    /**
     * Delivery confirmations arrive at least once, so a repeated one is not an error;
     * only an invoice in "sending" moves to "sent-to-client".
     */
    public function confirmDelivery(): DeliveryConfirmation
    {
        if ($this->status === StatusEnum::SentToClient) {
            return DeliveryConfirmation::AlreadyConfirmed;
        }

        if ($this->status !== StatusEnum::Sending) {
            return DeliveryConfirmation::NotAwaitingDelivery;
        }

        $this->status = StatusEnum::SentToClient;

        return DeliveryConfirmation::Confirmed;
    }

    public function id(): UuidInterface
    {
        return $this->id;
    }

    public function status(): StatusEnum
    {
        return $this->status;
    }

    public function customer(): Customer
    {
        return $this->customer;
    }

    /** @return list<ProductLine> */
    public function productLines(): array
    {
        return $this->productLines;
    }

    public function totalPrice(): int
    {
        return array_sum(array_map(
            static fn (ProductLine $line): int => $line->totalUnitPrice(),
            $this->productLines,
        ));
    }
}

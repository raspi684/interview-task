<?php

declare(strict_types=1);

namespace Modules\Invoices\Domain;

use Modules\Invoices\Domain\Enums\StatusEnum;
use Modules\Invoices\Domain\Exceptions\InvalidStatusTransition;
use Modules\Invoices\Domain\Exceptions\InvoiceCannotBeSent;
use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;

final class Invoice
{
    /** @param list<ProductLine> $productLines */
    private function __construct(
        private readonly UuidInterface $id,
        private StatusEnum $status,
        private readonly string $customerName,
        private readonly string $customerEmail,
        private readonly array $productLines,
    ) {}

    /** @param list<ProductLine> $productLines */
    public static function create(string $customerName, string $customerEmail, array $productLines): self
    {
        return new self(Uuid::uuid4(), StatusEnum::Draft, $customerName, $customerEmail, $productLines);
    }

    /** @param list<ProductLine> $productLines */
    public static function reconstitute(
        UuidInterface $id,
        StatusEnum $status,
        string $customerName,
        string $customerEmail,
        array $productLines,
    ): self {
        return new self($id, $status, $customerName, $customerEmail, $productLines);
    }

    public function send(): void
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

    public function markAsSentToClient(): void
    {
        if ($this->status !== StatusEnum::Sending) {
            throw InvalidStatusTransition::between($this->status, StatusEnum::SentToClient);
        }

        $this->status = StatusEnum::SentToClient;
    }

    public function id(): UuidInterface
    {
        return $this->id;
    }

    public function status(): StatusEnum
    {
        return $this->status;
    }

    public function customerName(): string
    {
        return $this->customerName;
    }

    public function customerEmail(): string
    {
        return $this->customerEmail;
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

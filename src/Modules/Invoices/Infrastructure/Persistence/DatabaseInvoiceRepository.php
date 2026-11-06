<?php

declare(strict_types=1);

namespace Modules\Invoices\Infrastructure\Persistence;

use DomainException;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Facades\Date;
use Modules\Invoices\Domain\Customer;
use Modules\Invoices\Domain\Enums\StatusEnum;
use Modules\Invoices\Domain\Invoice;
use Modules\Invoices\Domain\InvoiceRepositoryInterface;
use Modules\Invoices\Domain\ProductLine;
use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;
use UnexpectedValueException;

final readonly class DatabaseInvoiceRepository implements InvoiceRepositoryInterface
{
    public function __construct(
        private ConnectionInterface $connection,
    ) {}

    public function find(UuidInterface $id): ?Invoice
    {
        return $this->load($id, forUpdate: false);
    }

    public function findForUpdate(UuidInterface $id): ?Invoice
    {
        return $this->load($id, forUpdate: true);
    }

    public function save(Invoice $invoice): void
    {
        $now = Date::now();

        $this->connection->table('invoices')->upsert(
            [[
                'id' => $invoice->id()->toString(),
                'customer_name' => $invoice->customer()->name,
                'customer_email' => $invoice->customer()->email,
                'status' => $invoice->status()->value,
                'created_at' => $now,
                'updated_at' => $now,
            ]],
            uniqueBy: ['id'],
            update: ['customer_name', 'customer_email', 'status', 'updated_at'],
        );

        $lines = [];
        foreach ($invoice->productLines() as $line) {
            $lines[] = [
                'id' => $line->id->toString(),
                'invoice_id' => $invoice->id()->toString(),
                'name' => $line->productName,
                'quantity' => $line->quantity,
                'unit_price' => $line->unitPrice,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        if ($lines !== []) {
            $this->connection->table('invoice_product_lines')->upsert(
                $lines,
                uniqueBy: ['id'],
                update: ['name', 'quantity', 'unit_price', 'updated_at'],
            );
        }
    }

    private function load(UuidInterface $id, bool $forUpdate): ?Invoice
    {
        $query = $this->connection->table('invoices')->where('id', $id->toString());

        if ($forUpdate) {
            $query->lockForUpdate();
        }

        $row = $query->first();

        if ($row === null) {
            return null;
        }

        $lines = $this->connection->table('invoice_product_lines')
            ->where('invoice_id', $id->toString())
            // Line ids are UUIDv7, so ordering by id restores creation order.
            ->orderBy('id')
            ->get();

        // A stored row that breaks a domain rule is corrupt data on our side, not a client error,
        // so it must not surface as a domain exception (which the API maps to 4xx).
        try {
            return Invoice::reconstitute(
                id: Uuid::fromString($row->id),
                status: StatusEnum::from($row->status),
                customer: new Customer($row->customer_name, $row->customer_email),
                productLines: $lines
                    ->map(static fn (object $line): ProductLine => ProductLine::reconstitute(
                        id: Uuid::fromString($line->id),
                        productName: $line->name,
                        quantity: (int) $line->quantity,
                        unitPrice: (int) $line->unit_price,
                    ))
                    ->all(),
            );
        } catch (DomainException $exception) {
            throw new UnexpectedValueException(
                sprintf('Stored invoice %s violates domain rules.', $id->toString()),
                previous: $exception,
            );
        }
    }
}

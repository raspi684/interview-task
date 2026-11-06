<?php

declare(strict_types=1);

namespace Modules\Invoices\Application\Data;

use Modules\Invoices\Domain\ProductLine;

final readonly class ProductLineView
{
    public function __construct(
        public string $productName,
        public int $quantity,
        public int $unitPrice,
        public int $totalUnitPrice,
    ) {}

    public static function fromProductLine(ProductLine $line): self
    {
        return new self(
            productName: $line->productName,
            quantity: $line->quantity,
            unitPrice: $line->unitPrice,
            totalUnitPrice: $line->totalUnitPrice(),
        );
    }
}

<?php

declare(strict_types=1);

namespace Tests\Unit\Invoice\Application\Fakes;

use Closure;
use Illuminate\Database\ConnectionInterface;

trait InlineTransactions
{
    private function inlineTransactions(): ConnectionInterface
    {
        $connection = $this->createStub(ConnectionInterface::class);
        $connection->method('transaction')->willReturnCallback(
            static fn (Closure $callback): mixed => $callback(),
        );

        return $connection;
    }
}

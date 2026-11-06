<?php

declare(strict_types=1);

namespace Modules\Invoices\Infrastructure\Persistence;

use Closure;
use Illuminate\Database\ConnectionInterface;
use Modules\Invoices\Application\Ports\TransactionManagerInterface;

final readonly class DatabaseTransactionManager implements TransactionManagerInterface
{
    public function __construct(
        private ConnectionInterface $connection,
    ) {}

    public function run(Closure $callback): mixed
    {
        return $this->connection->transaction($callback);
    }
}

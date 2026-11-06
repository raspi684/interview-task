<?php

declare(strict_types=1);

namespace Tests\Unit\Invoice\Application\Fakes;

use Closure;
use Modules\Invoices\Application\Ports\TransactionManagerInterface;

final class InlineTransactionManager implements TransactionManagerInterface
{
    public function run(Closure $callback): mixed
    {
        return $callback();
    }
}

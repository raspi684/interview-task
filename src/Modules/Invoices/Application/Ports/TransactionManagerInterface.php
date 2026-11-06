<?php

declare(strict_types=1);

namespace Modules\Invoices\Application\Ports;

use Closure;

interface TransactionManagerInterface
{
    /**
     * Runs the callback atomically: any exception rolls back every change made inside it.
     *
     * @template T
     *
     * @param  Closure(): T  $callback
     * @return T
     */
    public function run(Closure $callback): mixed;
}

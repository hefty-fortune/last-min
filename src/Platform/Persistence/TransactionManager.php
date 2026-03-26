<?php

declare(strict_types=1);

namespace App\Platform\Persistence;

interface TransactionManager
{
    /**
     * @template T
     * @param callable(): T $callback
     * @return T
     */
    public function withinTransaction(callable $callback): mixed;
}

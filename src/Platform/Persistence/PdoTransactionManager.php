<?php

declare(strict_types=1);

namespace App\Platform\Persistence;

use PDO;

final class PdoTransactionManager implements TransactionManager
{
    public function __construct(private PDO $pdo)
    {
    }

    public function withinTransaction(callable $callback): mixed
    {
        $this->pdo->beginTransaction();

        try {
            $result = $callback();
            $this->pdo->commit();

            return $result;
        } catch (\Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }
    }
}

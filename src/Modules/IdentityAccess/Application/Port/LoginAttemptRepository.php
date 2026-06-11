<?php

declare(strict_types=1);

namespace App\Modules\IdentityAccess\Application\Port;

interface LoginAttemptRepository
{
    public function countRecentFailures(string $email, int $windowSeconds): int;

    public function recordFailure(string $email): void;

    public function clearFailures(string $email): void;
}

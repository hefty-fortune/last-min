<?php

declare(strict_types=1);

namespace App\Modules\IdentityAccess\Application\Port;

interface UserAuthRepository
{
    public function findByEmail(string $email): ?array;
    public function findByEmailWithRoles(string $email): ?array;
    public function setPasswordHash(string $userId, string $passwordHash): void;
}

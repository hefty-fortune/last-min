<?php

declare(strict_types=1);

namespace App\Modules\IdentityAccess\Application\Port;

interface AuthSessionRepository
{
    public function createSession(string $userId, string $plainToken, \DateTimeImmutable $expiresAt): array;
    public function findActiveByTokenHash(string $tokenHash): ?array;
    public function revokeByTokenHash(string $tokenHash): bool;
}

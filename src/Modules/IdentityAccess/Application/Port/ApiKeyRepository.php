<?php

declare(strict_types=1);

namespace App\Modules\IdentityAccess\Application\Port;

interface ApiKeyRepository
{
    public function create(string $clientId, string $name, string $plainApiKey): array;
    public function revokeByClientId(string $clientId): bool;
    public function findActiveByTokenHash(string $tokenHash): ?array;
}

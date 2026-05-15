<?php

declare(strict_types=1);

namespace App\Modules\IdentityAccess\Application\Port;

interface ApiKeyRepository
{
    /** @param list<string> $roles */
    public function createForActor(string $actorType, string $actorId, array $roles, string $name, string $plainApiKey, ?string $createdBy = null): array;
    public function revokeByApiKeyId(string $apiKeyId): bool;
    /** @return list<array<string, mixed>> */
    public function listAll(): array;
    public function findActiveByTokenHash(string $tokenHash): ?array;
}

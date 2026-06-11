<?php

declare(strict_types=1);

namespace App\Modules\AdminSetup\Application\Port;

interface UserRepository
{
    /** @param list<string> $roles */
    public function create(array $user, array $roles): array;
    /** @return array<string, mixed>|null */
    public function getById(string $userId): ?array;
    /** @return list<array<string, mixed>> */
    public function listByProviderId(?string $providerId): array;
    /** @param array<string, string> $fields */
    public function updateFields(string $userId, array $fields): array;
    /** @param list<string> $roles */
    public function replaceRoles(string $userId, array $roles): array;
    public function updatePasswordHash(string $userId, string $passwordHash): array;

    public function delete(string $userId): void;
}

<?php

declare(strict_types=1);

namespace App\Modules\AdminSetup\Application\Port;

interface AdminProviderRepository
{
    public function create(array $provider): array;
    public function existsById(string $providerId): bool;
    /** @return array<string, mixed>|null */
    public function getById(string $providerId): ?array;
    /** @return list<array<string, mixed>> */
    public function listByOrganizationId(?string $organizationId): array;
}

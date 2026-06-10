<?php

declare(strict_types=1);

namespace App\Modules\Providers\Application\Port;

interface ProviderRepository
{
    public function createIndividual(string $ownerUserProfileId): array;
    public function findById(string $providerId): ?array;

    /** @return array<string, mixed>|null */
    public function findByOwnerProfileId(string $ownerUserProfileId): ?array;

    /** @param array<string, mixed> $changes
     *  @return array<string, mixed>
     */
    public function update(string $providerId, array $changes): array;
}

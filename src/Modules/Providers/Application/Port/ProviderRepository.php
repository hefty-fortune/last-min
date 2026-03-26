<?php

declare(strict_types=1);

namespace App\Modules\Providers\Application\Port;

interface ProviderRepository
{
    public function createIndividual(string $ownerUserProfileId): array;
    public function findById(string $providerId): ?array;
}

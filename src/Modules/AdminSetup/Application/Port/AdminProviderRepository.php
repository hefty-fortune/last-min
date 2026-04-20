<?php

declare(strict_types=1);

namespace App\Modules\AdminSetup\Application\Port;

interface AdminProviderRepository
{
    public function create(array $provider): array;
    public function existsById(string $providerId): bool;
}

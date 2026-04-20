<?php

declare(strict_types=1);

namespace App\Modules\AdminSetup\Application\Port;

interface UserRepository
{
    /** @param list<string> $roles */
    public function create(array $user, array $roles): array;
    /** @return list<array<string, mixed>> */
    public function listByProviderId(?string $providerId): array;
}

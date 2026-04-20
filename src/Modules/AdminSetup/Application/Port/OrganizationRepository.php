<?php

declare(strict_types=1);

namespace App\Modules\AdminSetup\Application\Port;

interface OrganizationRepository
{
    public function create(array $organization): array;
    public function existsById(string $organizationId): bool;
}

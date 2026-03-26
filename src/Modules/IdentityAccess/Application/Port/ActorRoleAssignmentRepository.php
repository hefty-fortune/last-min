<?php

declare(strict_types=1);

namespace App\Modules\IdentityAccess\Application\Port;

interface ActorRoleAssignmentRepository
{
    /** @return list<string> */
    public function findActiveRolesByProfileId(string $profileId): array;
}

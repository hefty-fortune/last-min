<?php

declare(strict_types=1);

namespace App\Modules\Organizations\Application\Port;

interface OrganizationMemberRepository
{
    /** @return array<string, mixed>|null */
    public function findMember(string $organizationId, string $userProfileId): ?array;

    /** @return list<array<string, mixed>> */
    public function listMembers(string $organizationId): array;

    /** @return array<string, mixed> */
    public function addMember(string $organizationId, string $userProfileId, string $organizationRole): array;
}

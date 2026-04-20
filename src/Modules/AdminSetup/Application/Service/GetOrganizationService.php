<?php

declare(strict_types=1);

namespace App\Modules\AdminSetup\Application\Service;

use App\Common\Api\ApiError;
use App\Common\Api\ApiException;
use App\Common\Security\ActorContext;
use App\Modules\AdminSetup\Application\Port\OrganizationRepository;

final class GetOrganizationService
{
    public function __construct(private OrganizationRepository $organizations)
    {
    }

    public function getById(ActorContext $actor, string $organizationId): array
    {
        $this->assertAdmin($actor);

        $organization = $this->organizations->getById($organizationId);
        if ($organization === null) {
            throw new ApiException(404, new ApiError('ORGANIZATION_NOT_FOUND', 'Organization not found.'));
        }

        return $organization;
    }

    private function assertAdmin(ActorContext $actor): void
    {
        if (!$actor->hasRole('admin') && !$actor->hasRole('super-admin')) {
            throw new ApiException(403, new ApiError('FORBIDDEN_ROLE_MISSING', 'Admin role is required.'));
        }
    }
}

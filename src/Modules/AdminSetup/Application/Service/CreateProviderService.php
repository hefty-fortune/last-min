<?php

declare(strict_types=1);

namespace App\Modules\AdminSetup\Application\Service;

use App\Common\Api\ApiError;
use App\Common\Api\ApiException;
use App\Common\Security\ActorContext;
use App\Modules\AdminSetup\Application\Dto\CreateProviderRequest;
use App\Modules\AdminSetup\Application\Port\AdminProviderRepository;
use App\Modules\AdminSetup\Application\Port\OrganizationRepository;

final class CreateProviderService
{
    public function __construct(
        private OrganizationRepository $organizations,
        private AdminProviderRepository $providers,
    ) {
    }

    public function create(ActorContext $actor, CreateProviderRequest $request): array
    {
        $this->assertAdmin($actor);

        if (!$this->organizations->existsById($request->organizationId)) {
            throw new ApiException(422, new ApiError('VALIDATION_ORGANIZATION_NOT_FOUND', 'organization_id does not reference an existing organization.'));
        }
        if (trim($request->displayName) === '') {
            throw new ApiException(422, new ApiError('VALIDATION_REQUIRED_FIELD_MISSING', 'display_name is required.'));
        }
        if (!in_array($request->status, ['active', 'inactive'], true)) {
            throw new ApiException(422, new ApiError('VALIDATION_PROVIDER_STATUS_INVALID', 'status must be one of: active, inactive.'));
        }

        return $this->providers->create([
            'organization_id' => $request->organizationId,
            'display_name' => $request->displayName,
            'status' => $request->status,
        ]);
    }

    private function assertAdmin(ActorContext $actor): void
    {
        if (!$actor->hasRole('admin') && !$actor->hasRole('super-admin')) {
            throw new ApiException(403, new ApiError('FORBIDDEN_ROLE_MISSING', 'Admin role is required.'));
        }
    }
}

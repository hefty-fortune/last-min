<?php

declare(strict_types=1);

namespace App\Modules\AdminSetup\Application\Service;

use App\Common\Api\ApiError;
use App\Common\Api\ApiException;
use App\Common\Security\ActorContext;
use App\Modules\AdminSetup\Application\Port\OrganizationRepository;
use App\Platform\Audit\AuditLogger;

final class DeleteOrganizationService
{
    public function __construct(
        private OrganizationRepository $organizations,
        private AuditLogger $audit,
    ) {
    }

    /** @return array{organization_id: string, deleted: bool} */
    public function delete(ActorContext $actor, string $organizationId): array
    {
        $this->assertAdmin($actor);

        if ($this->organizations->getById($organizationId) === null) {
            throw new ApiException(404, new ApiError('ORGANIZATION_NOT_FOUND', 'Organization not found.'));
        }
        if ($this->organizations->countProviders($organizationId) > 0) {
            throw new ApiException(409, new ApiError('CONFLICT_ORGANIZATION_IN_USE', 'Organization still has providers; delete or reassign them first.'));
        }

        $this->organizations->delete($organizationId);
        $this->audit->record($actor, 'organization.delete', 'organization', $organizationId);

        return ['organization_id' => $organizationId, 'deleted' => true];
    }

    private function assertAdmin(ActorContext $actor): void
    {
        if (!$actor->hasRole('admin') && !$actor->hasRole('super-admin')) {
            throw new ApiException(403, new ApiError('FORBIDDEN_ROLE_MISSING', 'Admin role is required.'));
        }
    }
}

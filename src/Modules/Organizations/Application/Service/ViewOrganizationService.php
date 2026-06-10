<?php

declare(strict_types=1);

namespace App\Modules\Organizations\Application\Service;

use App\Common\Api\ApiError;
use App\Common\Api\ApiException;
use App\Common\Security\ActorContext;
use App\Modules\AdminSetup\Application\Port\OrganizationRepository;
use App\Modules\Organizations\Application\Port\OrganizationMemberRepository;

final class ViewOrganizationService
{
    public function __construct(
        private OrganizationRepository $organizations,
        private OrganizationMemberRepository $members,
    ) {
    }

    /** @return array<string, mixed> */
    public function getById(ActorContext $actor, string $organizationId): array
    {
        $organization = $this->organizations->getById($organizationId);
        if ($organization === null) {
            throw new ApiException(404, new ApiError('ORGANIZATION_NOT_FOUND', 'Organization not found.'));
        }

        $isAdmin = $actor->hasRole('admin') || $actor->hasRole('super-admin');
        if (!$isAdmin) {
            if ($actor->userProfileId === null || $this->members->findMember($organizationId, $actor->userProfileId) === null) {
                throw new ApiException(403, new ApiError('FORBIDDEN_ORGANIZATION_ACCESS', 'Actor is not a member of this organization.'));
            }
        }

        return $organization + ['members' => $this->members->listMembers($organizationId)];
    }
}

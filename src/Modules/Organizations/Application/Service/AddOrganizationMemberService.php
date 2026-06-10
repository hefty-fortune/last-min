<?php

declare(strict_types=1);

namespace App\Modules\Organizations\Application\Service;

use App\Common\Api\ApiError;
use App\Common\Api\ApiException;
use App\Common\Security\ActorContext;
use App\Modules\AdminSetup\Application\Port\OrganizationRepository;
use App\Modules\Organizations\Application\Port\OrganizationMemberRepository;

final class AddOrganizationMemberService
{
    // Assumed membership role taxonomy (open question in docs): owner|manager|staff.
    private const MEMBER_ROLES = ['owner', 'manager', 'staff'];
    private const MANAGING_ROLES = ['owner', 'manager'];

    public function __construct(
        private OrganizationRepository $organizations,
        private OrganizationMemberRepository $members,
    ) {
    }

    /** @param array<string, mixed> $payload
     *  @return array<string, mixed>
     */
    public function addMember(ActorContext $actor, string $organizationId, array $payload): array
    {
        if (!$this->organizations->existsById($organizationId)) {
            throw new ApiException(404, new ApiError('ORGANIZATION_NOT_FOUND', 'Organization not found.'));
        }

        $isAdmin = $actor->hasRole('admin') || $actor->hasRole('super-admin');
        if (!$isAdmin) {
            if ($actor->userProfileId === null) {
                throw new ApiException(401, new ApiError('AUTH_IDENTITY_NOT_LINKED', 'User profile linkage is required.'));
            }
            $membership = $this->members->findMember($organizationId, $actor->userProfileId);
            if ($membership === null || !in_array((string) $membership['organization_role'], self::MANAGING_ROLES, true)) {
                throw new ApiException(403, new ApiError('FORBIDDEN_POLICY_DENIED', 'Only organization owners or managers can add members.'));
            }
        }

        $userProfileId = trim((string) ($payload['user_profile_id'] ?? ''));
        if ($userProfileId === '') {
            throw new ApiException(422, new ApiError('VALIDATION_REQUIRED_FIELD_MISSING', 'user_profile_id is required.'));
        }

        $organizationRole = (string) ($payload['organization_role'] ?? '');
        if (!in_array($organizationRole, self::MEMBER_ROLES, true)) {
            throw new ApiException(422, new ApiError('VALIDATION_ORGANIZATION_ROLE_INVALID', 'organization_role must be owner, manager, or staff.'));
        }

        return $this->members->addMember($organizationId, $userProfileId, $organizationRole);
    }
}

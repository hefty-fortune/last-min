<?php

declare(strict_types=1);

namespace App\Modules\AdminSetup\Application\Service;

use App\Common\Api\ApiError;
use App\Common\Api\ApiException;
use App\Common\Security\ActorContext;
use App\Modules\AdminSetup\Application\Port\UserRepository;

final class UpdateUserRolesService
{
    public function __construct(private UserRepository $users)
    {
    }

    public function update(ActorContext $actor, string $userId, array $roles): array
    {
        $this->assertAdmin($actor);

        $user = $this->users->getById($userId);
        if ($user === null) {
            throw new ApiException(404, new ApiError('USER_NOT_FOUND', 'User not found.'));
        }

        if ($roles === []) {
            throw new ApiException(422, new ApiError('VALIDATION_ROLES_REQUIRED', 'roles must contain at least one role.'));
        }

        $normalizedRoles = [];
        foreach ($roles as $role) {
            $normalizedRole = trim((string) $role);
            if ($normalizedRole === '') {
                throw new ApiException(422, new ApiError('VALIDATION_ROLES_INVALID', 'roles must not contain empty values.'));
            }
            $normalizedRoles[] = $normalizedRole;
        }

        return $this->users->replaceRoles($userId, array_values(array_unique($normalizedRoles)));
    }

    private function assertAdmin(ActorContext $actor): void
    {
        if (!$actor->hasRole('admin') && !$actor->hasRole('super-admin') && !$actor->hasRole('org_admin')) {
            throw new ApiException(403, new ApiError('FORBIDDEN_ROLE_MISSING', 'Admin or org_admin role is required.'));
        }
    }
}

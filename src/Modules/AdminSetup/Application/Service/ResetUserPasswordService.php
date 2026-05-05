<?php

declare(strict_types=1);

namespace App\Modules\AdminSetup\Application\Service;

use App\Common\Api\ApiError;
use App\Common\Api\ApiException;
use App\Common\Security\ActorContext;
use App\Modules\AdminSetup\Application\Port\UserRepository;

final class ResetUserPasswordService
{
    public function __construct(private UserRepository $users)
    {
    }

    public function reset(ActorContext $actor, string $userId, string $password): array
    {
        $this->assertAdmin($actor);

        $user = $this->users->getById($userId);
        if ($user === null) {
            throw new ApiException(404, new ApiError('USER_NOT_FOUND', 'User not found.'));
        }

        if (trim($password) === '') {
            throw new ApiException(422, new ApiError('VALIDATION_REQUIRED_FIELD_MISSING', 'password is required.'));
        }

        $passwordHash = password_hash($password, PASSWORD_BCRYPT);

        return $this->users->updatePasswordHash($userId, $passwordHash);
    }

    private function assertAdmin(ActorContext $actor): void
    {
        if (!$actor->hasRole('admin') && !$actor->hasRole('super-admin') && !$actor->hasRole('org_admin')) {
            throw new ApiException(403, new ApiError('FORBIDDEN_ROLE_MISSING', 'Admin or org_admin role is required.'));
        }
    }
}

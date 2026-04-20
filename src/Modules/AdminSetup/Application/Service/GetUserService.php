<?php

declare(strict_types=1);

namespace App\Modules\AdminSetup\Application\Service;

use App\Common\Api\ApiError;
use App\Common\Api\ApiException;
use App\Common\Security\ActorContext;
use App\Modules\AdminSetup\Application\Port\UserRepository;

final class GetUserService
{
    public function __construct(private UserRepository $users)
    {
    }

    public function getById(ActorContext $actor, string $userId): array
    {
        $this->assertAdmin($actor);

        $user = $this->users->getById($userId);
        if ($user === null) {
            throw new ApiException(404, new ApiError('USER_NOT_FOUND', 'User not found.'));
        }

        return $user;
    }

    private function assertAdmin(ActorContext $actor): void
    {
        if (!$actor->hasRole('admin') && !$actor->hasRole('super-admin')) {
            throw new ApiException(403, new ApiError('FORBIDDEN_ROLE_MISSING', 'Admin role is required.'));
        }
    }
}

<?php

declare(strict_types=1);

namespace App\Modules\AdminSetup\Application\Service;

use App\Common\Api\ApiError;
use App\Common\Api\ApiException;
use App\Common\Security\ActorContext;
use App\Modules\AdminSetup\Application\Port\UserRepository;
use App\Platform\Audit\AuditLogger;

final class DeleteUserService
{
    public function __construct(
        private UserRepository $users,
        private AuditLogger $audit,
    ) {
    }

    /** @return array{user_id: string, deleted: bool} */
    public function delete(ActorContext $actor, string $userId): array
    {
        $this->assertAdmin($actor);

        if ($actor->actorId === $userId) {
            throw new ApiException(409, new ApiError('CONFLICT_CANNOT_DELETE_SELF', 'You cannot delete your own account.'));
        }
        if ($this->users->getById($userId) === null) {
            throw new ApiException(404, new ApiError('USER_NOT_FOUND', 'User not found.'));
        }

        $this->users->delete($userId);
        $this->audit->record($actor, 'user.delete', 'user', $userId);

        return ['user_id' => $userId, 'deleted' => true];
    }

    private function assertAdmin(ActorContext $actor): void
    {
        if (!$actor->hasRole('admin') && !$actor->hasRole('super-admin')) {
            throw new ApiException(403, new ApiError('FORBIDDEN_ROLE_MISSING', 'Admin role is required.'));
        }
    }
}

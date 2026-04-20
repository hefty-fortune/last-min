<?php

declare(strict_types=1);

namespace App\Modules\AdminSetup\Application\Service;

use App\Common\Api\ApiError;
use App\Common\Api\ApiException;
use App\Common\Security\ActorContext;
use App\Modules\AdminSetup\Application\Port\UserRepository;

final class ListUsersService
{
    public function __construct(private UserRepository $users)
    {
    }

    public function list(ActorContext $actor, ?string $providerId): array
    {
        $this->assertAdmin($actor);

        return $this->users->listByProviderId($providerId);
    }

    private function assertAdmin(ActorContext $actor): void
    {
        if (!$actor->hasRole('admin') && !$actor->hasRole('super-admin')) {
            throw new ApiException(403, new ApiError('FORBIDDEN_ROLE_MISSING', 'Admin role is required.'));
        }
    }
}

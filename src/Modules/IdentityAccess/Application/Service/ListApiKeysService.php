<?php

declare(strict_types=1);

namespace App\Modules\IdentityAccess\Application\Service;

use App\Common\Api\ApiError;
use App\Common\Api\ApiException;
use App\Common\Security\ActorContext;
use App\Modules\IdentityAccess\Application\Port\ApiKeyRepository;

final class ListApiKeysService
{
    public function __construct(private ApiKeyRepository $keys)
    {
    }

    public function list(ActorContext $actor): array
    {
        $this->assertAdmin($actor);

        return $this->keys->listAll();
    }

    private function assertAdmin(ActorContext $actor): void
    {
        if (!$actor->hasRole('admin') && !$actor->hasRole('super-admin')) {
            throw new ApiException(403, new ApiError('FORBIDDEN_ROLE_MISSING', 'Admin role is required.'));
        }
    }
}

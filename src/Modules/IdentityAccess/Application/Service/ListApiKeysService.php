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

    public function list(ActorContext $actor, string $clientId): array
    {
        $this->assertAdmin($actor);

        if (!preg_match('/^[a-f0-9-]{36}$/i', $clientId)) {
            throw new ApiException(422, new ApiError('VALIDATION_CLIENT_ID_INVALID', 'client_id must be a UUID string.'));
        }

        return $this->keys->listByClientId($clientId);
    }

    private function assertAdmin(ActorContext $actor): void
    {
        if (!$actor->hasRole('admin') && !$actor->hasRole('super-admin')) {
            throw new ApiException(403, new ApiError('FORBIDDEN_ROLE_MISSING', 'Admin role is required.'));
        }
    }
}

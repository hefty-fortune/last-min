<?php

declare(strict_types=1);

namespace App\Modules\IdentityAccess\Application\Service;

use App\Common\Api\ApiError;
use App\Common\Api\ApiException;
use App\Common\Security\ActorContext;
use App\Modules\IdentityAccess\Application\Port\ApiKeyRepository;

final class DeleteApiKeyService
{
    public function __construct(private ApiKeyRepository $keys)
    {
    }

    public function delete(ActorContext $actor, string $clientId): void
    {
        $this->assertAdmin($actor);

        if (trim($clientId) === '') {
            throw new ApiException(422, new ApiError('VALIDATION_REQUIRED_FIELD_MISSING', 'client_id is required.'));
        }

        if (!$this->keys->revokeByClientId($clientId)) {
            throw new ApiException(404, new ApiError('API_KEY_NOT_FOUND', 'No active API key found for provided client_id.'));
        }
    }

    private function assertAdmin(ActorContext $actor): void
    {
        if (!$actor->hasRole('admin') && !$actor->hasRole('super-admin')) {
            throw new ApiException(403, new ApiError('FORBIDDEN_ROLE_MISSING', 'Admin role is required.'));
        }
    }
}

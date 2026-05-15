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

    public function delete(ActorContext $actor, string $apiKeyId): void
    {
        $this->assertAdmin($actor);

        if (!preg_match('/^[a-f0-9-]{36}$/i', $apiKeyId)) {
            throw new ApiException(422, new ApiError('VALIDATION_API_KEY_ID_INVALID', 'api_key_id must be a UUID string.'));
        }

        if (!$this->keys->revokeByApiKeyId($apiKeyId)) {
            throw new ApiException(404, new ApiError('API_KEY_NOT_FOUND', 'No active API key found for provided api_key_id.'));
        }
    }

    public function destroy(ActorContext $actor, string $apiKeyId): void
    {
        $this->assertAdmin($actor);

        if (!preg_match('/^[a-f0-9-]{36}$/i', $apiKeyId)) {
            throw new ApiException(422, new ApiError('VALIDATION_API_KEY_ID_INVALID', 'api_key_id must be a UUID string.'));
        }

        if (!$this->keys->destroyByApiKeyId($apiKeyId)) {
            throw new ApiException(404, new ApiError('API_KEY_NOT_FOUND', 'No API key found for provided api_key_id.'));
        }
    }

    private function assertAdmin(ActorContext $actor): void
    {
        if (!$actor->hasRole('admin') && !$actor->hasRole('super-admin')) {
            throw new ApiException(403, new ApiError('FORBIDDEN_ROLE_MISSING', 'Admin role is required.'));
        }
    }
}

<?php

declare(strict_types=1);

namespace App\Modules\IdentityAccess\Application\Service;

use App\Common\Api\ApiError;
use App\Common\Api\ApiException;
use App\Common\Security\ActorContext;
use App\Modules\IdentityAccess\Application\Dto\CreateApiKeyRequest;
use App\Modules\IdentityAccess\Application\Port\ApiKeyRepository;

final class CreateApiKeyService
{
    public function __construct(private ApiKeyRepository $keys)
    {
    }

    public function create(ActorContext $actor, CreateApiKeyRequest $request): array
    {
        $this->assertAdmin($actor);

        if (trim($request->name) === '') {
            throw new ApiException(422, new ApiError('VALIDATION_REQUIRED_FIELD_MISSING', 'name is required.'));
        }

        $token = $this->generateToken();
        $stored = $this->keys->createForActor(
            $actor->roles[0] ?? 'admin',
            $actor->actorId,
            $actor->roles,
            $request->name,
            $token,
            $actor->actorId,
        );

        return [
            'api_key_id' => $stored['api_key_id'],
            'name' => $stored['name'],
            'api_key' => $token,
        ];
    }

    private function assertAdmin(ActorContext $actor): void
    {
        if (!$actor->hasRole('admin') && !$actor->hasRole('super-admin')) {
            throw new ApiException(403, new ApiError('FORBIDDEN_ROLE_MISSING', 'Admin role is required.'));
        }
    }

    private function generateToken(): string
    {
        return 'lm_' . bin2hex(random_bytes(20));
    }
}

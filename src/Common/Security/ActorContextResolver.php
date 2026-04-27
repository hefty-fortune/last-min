<?php

declare(strict_types=1);

namespace App\Common\Security;

use App\Common\Api\ApiError;
use App\Common\Api\ApiException;

final class ActorContextResolver
{
    public function __construct(
        private ?BearerTokenActorResolver $bearerTokens = null,
        private ?ApiKeyGateMiddleware $apiKeyGate = null,
    ) {
    }

    public function resolve(array $headers): ActorContext
    {
        if ($this->apiKeyGate !== null) {
            $this->apiKeyGate->validate($headers);
        }

        $subject = $headers['X-Actor-Subject'] ?? $headers['x-actor-subject'] ?? null;
        $actorId = $headers['X-Actor-Id'] ?? $headers['x-actor-id'] ?? null;
        $roles = $headers['X-Actor-Roles'] ?? $headers['x-actor-roles'] ?? '';
        $profileId = $headers['X-User-Profile-Id'] ?? $headers['x-user-profile-id'] ?? null;

        if ($subject !== null && $actorId !== null) {
            $roleList = array_values(array_filter(array_map('trim', explode(',', (string) $roles))));
            return new ActorContext((string) $actorId, (string) $subject, $roleList, $profileId !== null ? (string) $profileId : null);
        }

        $authHeader = $headers['Authorization'] ?? $headers['authorization'] ?? null;
        if (is_string($authHeader) && str_starts_with($authHeader, 'Bearer ') && $this->bearerTokens !== null) {
            $token = trim(substr($authHeader, 7));
            $actor = $this->bearerTokens->resolve($token);
            if ($actor !== null) {
                return $actor;
            }
        }

        throw new ApiException(401, new ApiError('AUTH_IDENTITY_NOT_LINKED', 'Missing upstream actor linkage.'));
    }
}

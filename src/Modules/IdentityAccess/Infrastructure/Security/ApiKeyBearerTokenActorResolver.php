<?php

declare(strict_types=1);

namespace App\Modules\IdentityAccess\Infrastructure\Security;

use App\Common\Security\ActorContext;
use App\Common\Security\BearerTokenActorResolver;
use App\Modules\IdentityAccess\Application\Port\ApiKeyRepository;

final class ApiKeyBearerTokenActorResolver implements BearerTokenActorResolver
{
    public function __construct(private ApiKeyRepository $keys)
    {
    }

    public function resolve(string $token): ?ActorContext
    {
        if (trim($token) === '') {
            return null;
        }

        $key = $this->keys->findActiveByTokenHash(hash('sha256', $token));
        if ($key === null) {
            return null;
        }

        $actorType = isset($key['actor_type']) ? (string) $key['actor_type'] : 'client';
        $actorId = isset($key['actor_id']) && is_string($key['actor_id']) && trim($key['actor_id']) !== ''
            ? (string) $key['actor_id']
            : (string) $key['client_id'];
        $roles = $this->extractRoles($key, $actorType);

        return new ActorContext(actorId: $actorId, upstreamSubject: 'api-key:' . (string) $key['id'], roles: $roles, userProfileId: null);
    }

    /** @param array<string, mixed> $key */
    private function extractRoles(array $key, string $actorType): array
    {
        $rawRoles = $key['actor_roles'] ?? null;
        if (!is_string($rawRoles) || trim($rawRoles) === '') {
            return [$actorType === 'super-admin' ? 'super-admin' : $actorType];
        }

        $decoded = json_decode($rawRoles, true);
        if (!is_array($decoded)) {
            return [$actorType === 'super-admin' ? 'super-admin' : $actorType];
        }

        $roles = [];
        foreach ($decoded as $role) {
            if (is_string($role) && trim($role) !== '') {
                $roles[] = trim($role);
            }
        }

        return $roles === [] ? [$actorType === 'super-admin' ? 'super-admin' : $actorType] : array_values(array_unique($roles));
    }
}

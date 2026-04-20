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

        return new ActorContext(
            actorId: (string) $key['client_id'],
            upstreamSubject: 'api-key:' . (string) $key['id'],
            roles: ['client'],
            userProfileId: null,
        );
    }
}

<?php

declare(strict_types=1);

namespace App\Common\Security;

use App\Common\Api\ApiError;
use App\Common\Api\ApiException;

final class ActorContextResolver
{
    public function resolve(array $headers): ActorContext
    {
        $subject = $headers['X-Actor-Subject'] ?? $headers['x-actor-subject'] ?? null;
        $actorId = $headers['X-Actor-Id'] ?? $headers['x-actor-id'] ?? null;
        $roles = $headers['X-Actor-Roles'] ?? $headers['x-actor-roles'] ?? '';
        $profileId = $headers['X-User-Profile-Id'] ?? $headers['x-user-profile-id'] ?? null;

        if ($subject === null || $actorId === null) {
            throw new ApiException(401, new ApiError('AUTH_IDENTITY_NOT_LINKED', 'Missing upstream actor linkage.'));
        }

        $roleList = array_values(array_filter(array_map('trim', explode(',', (string) $roles))));

        return new ActorContext((string) $actorId, (string) $subject, $roleList, $profileId !== null ? (string) $profileId : null);
    }
}

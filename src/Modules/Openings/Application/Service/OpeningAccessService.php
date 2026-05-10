<?php

declare(strict_types=1);

namespace App\Modules\Openings\Application\Service;

use App\Common\Api\ApiError;
use App\Common\Api\ApiException;
use App\Common\Security\ActorContext;
use App\Modules\Providers\Application\Port\ProviderRepository;

final class OpeningAccessService
{
    public function __construct(private ProviderRepository $providers)
    {
    }

    /** @return array<string, mixed> */
    public function assertCanReadProvider(ActorContext $actor, string $providerId): array
    {
        $provider = $this->providers->findById($providerId);
        if ($provider === null) {
            throw new ApiException(404, new ApiError('PROVIDER_NOT_FOUND', 'Provider not found.'));
        }

        if ($actor->hasRole('admin') || $actor->hasRole('super-admin')) {
            return $provider;
        }

        if (!$actor->hasRole('provider')) {
            throw new ApiException(403, new ApiError('FORBIDDEN_ROLE_MISSING', 'Provider role is required.'));
        }

        if ($actor->userProfileId === null) {
            throw new ApiException(401, new ApiError('AUTH_IDENTITY_NOT_LINKED', 'User profile linkage is required.'));
        }

        if (($provider['owner_user_profile_id'] ?? null) !== $actor->userProfileId) {
            throw new ApiException(403, new ApiError('FORBIDDEN_PROVIDER_ACCESS', 'Actor cannot access this provider.'));
        }

        return $provider;
    }

    /** @return array<string, mixed> */
    public function assertCanManageProvider(ActorContext $actor, string $providerId): array
    {
        return $this->assertCanReadProvider($actor, $providerId);
    }
}

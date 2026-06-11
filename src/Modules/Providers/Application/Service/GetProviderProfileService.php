<?php

declare(strict_types=1);

namespace App\Modules\Providers\Application\Service;

use App\Common\Api\ApiError;
use App\Common\Api\ApiException;
use App\Common\Security\ActorContext;
use App\Modules\Providers\Application\Port\ProviderRepository;

final class GetProviderProfileService
{
    public function __construct(private ProviderRepository $providers)
    {
    }

    /** @return array<string, mixed> */
    public function getById(ActorContext $actor, string $providerId): array
    {
        $provider = $this->providers->findById($providerId);
        if ($provider === null) {
            throw new ApiException(404, new ApiError('PROVIDER_NOT_FOUND', 'Provider not found.'));
        }

        if (!$actor->hasRole('admin') && !$actor->hasRole('super-admin')) {
            if (!$actor->hasRole('provider') || $actor->userProfileId === null || ($provider['owner_user_profile_id'] ?? null) !== $actor->userProfileId) {
                throw new ApiException(403, new ApiError('FORBIDDEN_PROVIDER_ACCESS', 'Actor cannot access this provider.'));
            }
        }

        return self::mapProvider($provider);
    }

    /** @return array<string, mixed> */
    public function getMine(ActorContext $actor): array
    {
        if ($actor->userProfileId === null) {
            throw new ApiException(401, new ApiError('AUTH_IDENTITY_NOT_LINKED', 'User profile linkage is required.'));
        }

        $provider = $this->providers->findByOwnerProfileId($actor->userProfileId);
        if ($provider === null) {
            throw new ApiException(404, new ApiError('PROVIDER_NOT_LINKED', 'No provider profile is linked to this account.'));
        }

        return self::mapProvider($provider);
    }

    /** @param array<string, mixed> $row
     *  @return array<string, mixed>
     */
    public static function mapProvider(array $row): array
    {
        return [
            'provider_id' => $row['id'],
            'provider_type' => $row['provider_type'],
            'status' => $row['status'],
            'display_name' => $row['display_name'],
            'organization_id' => $row['organization_id'],
            'created_at' => $row['created_at'],
            'updated_at' => $row['updated_at'],
        ];
    }
}

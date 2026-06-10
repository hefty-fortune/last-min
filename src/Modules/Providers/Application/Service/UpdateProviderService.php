<?php

declare(strict_types=1);

namespace App\Modules\Providers\Application\Service;

use App\Common\Api\ApiError;
use App\Common\Api\ApiException;
use App\Common\Security\ActorContext;
use App\Modules\Providers\Application\Port\ProviderRepository;

final class UpdateProviderService
{
    public function __construct(private ProviderRepository $providers)
    {
    }

    /** @param array<string, mixed> $payload
     *  @return array<string, mixed>
     */
    public function update(ActorContext $actor, string $providerId, array $payload): array
    {
        $provider = $this->providers->findById($providerId);
        if ($provider === null) {
            throw new ApiException(404, new ApiError('PROVIDER_NOT_FOUND', 'Provider not found.'));
        }

        $isAdmin = $actor->hasRole('admin') || $actor->hasRole('super-admin');
        $isOwner = $actor->hasRole('provider')
            && $actor->userProfileId !== null
            && ($provider['owner_user_profile_id'] ?? null) === $actor->userProfileId;

        if (!$isAdmin && !$isOwner) {
            throw new ApiException(403, new ApiError('FORBIDDEN_PROVIDER_ACCESS', 'Actor cannot manage this provider.'));
        }

        $changes = [];

        if (array_key_exists('display_name', $payload)) {
            $displayName = trim((string) $payload['display_name']);
            if ($displayName === '') {
                throw new ApiException(422, new ApiError('VALIDATION_REQUIRED_FIELD', 'display_name must not be empty.'));
            }
            $changes['display_name'] = $displayName;
        }

        if (array_key_exists('status', $payload)) {
            // Status transitions (suspend/reactivate) are an operational concern.
            if (!$isAdmin) {
                throw new ApiException(403, new ApiError('FORBIDDEN_PROVIDER_STATUS_CHANGE', 'Only admins can change provider status.'));
            }
            $status = (string) $payload['status'];
            if (!in_array($status, ['onboarding', 'active', 'suspended'], true)) {
                throw new ApiException(422, new ApiError('VALIDATION_STATUS_INVALID', 'status must be onboarding, active, or suspended.'));
            }
            $changes['status'] = $status;
        }

        if ($changes === []) {
            return GetProviderProfileService::mapProvider($provider);
        }

        return GetProviderProfileService::mapProvider($this->providers->update($providerId, $changes));
    }
}

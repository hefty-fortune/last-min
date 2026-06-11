<?php

declare(strict_types=1);

namespace App\Modules\Booking\Application\Service;

use App\Common\Api\ApiError;
use App\Common\Api\ApiException;
use App\Common\Security\ActorContext;
use App\Modules\Booking\Application\Port\BookingRepository;
use App\Modules\Providers\Application\Port\ProviderRepository;

final class ListProviderBookingsService
{
    public function __construct(
        private BookingRepository $bookings,
        private ProviderRepository $providers,
    ) {
    }

    /** @return list<array<string, mixed>> */
    public function listForProvider(ActorContext $actor, string $providerId, ?string $state, int $limit): array
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

        return $this->bookings->listByProviderId($providerId, $state, $limit);
    }
}

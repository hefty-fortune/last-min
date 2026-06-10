<?php

declare(strict_types=1);

namespace App\Modules\Booking\Application\Service;

use App\Common\Api\ApiError;
use App\Common\Api\ApiException;
use App\Common\Security\ActorContext;
use App\Modules\Booking\Application\Port\BookingRepository;

final class ListMyBookingsService
{
    public function __construct(private BookingRepository $bookings)
    {
    }

    /** @return list<array<string, mixed>> */
    public function listForActor(ActorContext $actor, ?string $state, int $limit): array
    {
        if (!$actor->hasRole('client')) {
            throw new ApiException(403, new ApiError('FORBIDDEN_ROLE_MISSING', 'Client role is required.'));
        }
        if ($actor->userProfileId === null) {
            throw new ApiException(401, new ApiError('AUTH_IDENTITY_NOT_LINKED', 'User profile linkage is required.'));
        }

        return $this->bookings->listByClientProfileId($actor->userProfileId, $state, $limit);
    }
}

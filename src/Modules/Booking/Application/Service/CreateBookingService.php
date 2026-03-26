<?php

declare(strict_types=1);

namespace App\Modules\Booking\Application\Service;

use App\Common\Api\ApiError;
use App\Common\Api\ApiException;
use App\Common\Security\ActorContext;
use App\Modules\Booking\Application\Dto\CreateBookingRequest;
use App\Modules\Booking\Application\Port\BookingRepository;
use App\Modules\Openings\Application\Port\OpeningRepository;
use App\Platform\Persistence\TransactionManager;

final class CreateBookingService
{
    public function __construct(
        private TransactionManager $tx,
        private OpeningRepository $openings,
        private BookingRepository $bookings,
    ) {
    }

    public function create(ActorContext $actor, CreateBookingRequest $request): array
    {
        if (!$actor->hasRole('client')) {
            throw new ApiException(403, new ApiError('FORBIDDEN_ROLE_MISSING', 'Client role is required.'));
        }
        if ($actor->userProfileId === null) {
            throw new ApiException(401, new ApiError('AUTH_IDENTITY_NOT_LINKED', 'User profile linkage is required.'));
        }

        return $this->tx->withinTransaction(function () use ($actor, $request): array {
            $opening = $this->openings->lockById($request->openingId);
            if ($opening === null) {
                throw new ApiException(404, new ApiError('BOOKING_OPENING_NOT_FOUND', 'Opening was not found.'));
            }
            if ($opening['status'] !== 'published') {
                throw new ApiException(409, new ApiError('BOOKING_OPENING_NOT_PUBLISHED', 'Opening is not currently published.'));
            }
            if ($this->bookings->hasActiveBookingForOpening($request->openingId)) {
                throw new ApiException(409, new ApiError('BOOKING_OPENING_ALREADY_RESERVED', 'The selected opening is no longer available.'));
            }

            $booking = $this->bookings->createReserved([
                'opening_id' => $request->openingId,
                'provider_id' => $opening['provider_id'],
                'client_user_profile_id' => $actor->userProfileId,
                'payment_required_amount' => (int) $opening['price_amount'],
                'payment_currency' => $opening['price_currency'],
            ]);
            $this->openings->updateStatus($request->openingId, 'reserved');

            return $booking;
        });
    }
}

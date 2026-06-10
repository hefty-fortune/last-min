<?php

declare(strict_types=1);

namespace App\Modules\Refunds\Application\Service;

use App\Common\Api\ApiError;
use App\Common\Api\ApiException;
use App\Common\Security\ActorContext;
use App\Modules\Booking\Application\Port\BookingRepository;
use App\Modules\Providers\Application\Port\ProviderRepository;
use App\Modules\Refunds\Application\Port\RefundRepository;

final class ListBookingRefundsService
{
    public function __construct(
        private RefundRepository $refunds,
        private BookingRepository $bookings,
        private ProviderRepository $providers,
    ) {
    }

    /** @return list<array<string, mixed>> */
    public function listForBooking(ActorContext $actor, string $bookingId): array
    {
        $booking = $this->bookings->findById($bookingId);
        if ($booking === null) {
            throw new ApiException(404, new ApiError('BOOKING_NOT_FOUND', 'Booking was not found.'));
        }

        $this->assertCanReadBookingRefunds($actor, $booking);

        return $this->refunds->listByBookingId($bookingId);
    }

    /** @param array<string, mixed> $booking */
    private function assertCanReadBookingRefunds(ActorContext $actor, array $booking): void
    {
        if ($actor->hasRole('admin') || $actor->hasRole('super-admin')) {
            return;
        }

        if ($actor->userProfileId === null) {
            throw new ApiException(401, new ApiError('AUTH_IDENTITY_NOT_LINKED', 'User profile linkage is required.'));
        }

        if ($actor->hasRole('client') && $booking['client_user_profile_id'] === $actor->userProfileId) {
            return;
        }

        if ($actor->hasRole('provider')) {
            $provider = $this->providers->findById((string) $booking['provider_id']);
            if ($provider !== null && ($provider['owner_user_profile_id'] ?? null) === $actor->userProfileId) {
                return;
            }
        }

        throw new ApiException(403, new ApiError('FORBIDDEN_REFUND_SCOPE', 'Actor cannot access refunds for this booking.'));
    }
}

<?php

declare(strict_types=1);

namespace App\Modules\Booking\Application\Service;

use App\Common\Api\ApiError;
use App\Common\Api\ApiException;
use App\Common\Security\ActorContext;
use App\Modules\Booking\Application\Port\BookingRepository;
use App\Modules\Providers\Application\Port\ProviderRepository;
use App\Modules\Refunds\Application\Service\RequestRefundService;
use App\Platform\Persistence\TransactionManager;

final class MarkNoShowService
{
    public function __construct(
        private TransactionManager $tx,
        private BookingRepository $bookings,
        private ProviderRepository $providers,
        private RequestRefundService $refundRequests,
    ) {
    }

    /** @return array<string, mixed> */
    public function markProviderNoShow(ActorContext $actor, string $bookingId): array
    {
        return $this->mark($actor, $bookingId, 'provider', 'provider_no_show');
    }

    /** @return array<string, mixed> */
    public function markClientNoShow(ActorContext $actor, string $bookingId): array
    {
        // Locked business rule: client no-show is a consumed service, no refund.
        return $this->mark($actor, $bookingId, 'client', 'client_no_show');
    }

    /** @return array<string, mixed> */
    private function mark(ActorContext $actor, string $bookingId, string $noShowActor, string $targetState): array
    {
        return $this->tx->withinTransaction(function () use ($actor, $bookingId, $noShowActor, $targetState): array {
            $booking = $this->bookings->lockById($bookingId);
            if ($booking === null) {
                throw new ApiException(404, new ApiError('BOOKING_NOT_FOUND', 'Booking was not found.'));
            }

            $this->assertCanMarkNoShow($actor, $booking);

            if ($booking['state'] !== 'confirmed') {
                throw new ApiException(409, new ApiError('BOOKING_STATE_INVALID', 'No-show can only be recorded for confirmed bookings.'));
            }

            $updated = $this->bookings->markNoShow($bookingId, $noShowActor, $targetState);

            if ($targetState === 'provider_no_show') {
                // Locked business rule: provider no-show triggers the refund
                // workflow, atomically with the state transition.
                $this->refundRequests->requestForBooking($bookingId, 'provider_no_show');
            }

            return $updated;
        });
    }

    /** @param array<string, mixed> $booking */
    private function assertCanMarkNoShow(ActorContext $actor, array $booking): void
    {
        if ($actor->hasRole('admin') || $actor->hasRole('super-admin')) {
            return;
        }

        if (!$actor->hasRole('provider')) {
            throw new ApiException(403, new ApiError('FORBIDDEN_ROLE_MISSING', 'Provider or admin role is required.'));
        }

        if ($actor->userProfileId === null) {
            throw new ApiException(401, new ApiError('AUTH_IDENTITY_NOT_LINKED', 'User profile linkage is required.'));
        }

        $provider = $this->providers->findById((string) $booking['provider_id']);
        if ($provider === null || ($provider['owner_user_profile_id'] ?? null) !== $actor->userProfileId) {
            throw new ApiException(403, new ApiError('FORBIDDEN_BOOKING_SCOPE', 'Actor cannot manage this booking.'));
        }
    }
}

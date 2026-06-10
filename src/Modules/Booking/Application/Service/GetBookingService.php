<?php

declare(strict_types=1);

namespace App\Modules\Booking\Application\Service;

use App\Common\Api\ApiError;
use App\Common\Api\ApiException;
use App\Common\Security\ActorContext;
use App\Modules\Booking\Application\Port\BookingRepository;
use App\Modules\Payments\Application\Port\PaymentRepository;
use App\Modules\Providers\Application\Port\ProviderRepository;

final class GetBookingService
{
    public function __construct(
        private BookingRepository $bookings,
        private PaymentRepository $payments,
        private ProviderRepository $providers,
    ) {
    }

    /** @return array<string, mixed> */
    public function getById(ActorContext $actor, string $bookingId): array
    {
        $booking = $this->bookings->findDetailById($bookingId);
        if ($booking === null) {
            throw new ApiException(404, new ApiError('BOOKING_NOT_FOUND', 'Booking was not found.'));
        }

        $this->assertCanReadBooking($actor, $booking);

        $booking['payment'] = $this->paymentSummary($bookingId);

        return $booking;
    }

    /** @param array<string, mixed> $booking */
    private function assertCanReadBooking(ActorContext $actor, array $booking): void
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

        throw new ApiException(403, new ApiError('FORBIDDEN_BOOKING_SCOPE', 'Actor cannot access this booking.'));
    }

    /** @return array<string, mixed>|null */
    private function paymentSummary(string $bookingId): ?array
    {
        $payment = $this->payments->findByBookingId($bookingId);
        if ($payment === null) {
            return null;
        }

        return [
            'payment_id' => $payment['id'],
            'state' => $payment['state'],
            'amount' => ['currency' => $payment['currency'], 'amount_minor' => (int) $payment['amount']],
            'stripe_payment_intent_id' => $payment['stripe_payment_intent_id'],
        ];
    }
}

<?php

declare(strict_types=1);

namespace App\Modules\Payments\Application\Service;

use App\Common\Api\ApiError;
use App\Common\Api\ApiException;
use App\Modules\Booking\Application\Port\BookingRepository;
use App\Modules\Openings\Application\Port\OpeningRepository;
use App\Modules\Payments\Application\Port\PaymentRepository;
use App\Platform\Persistence\TransactionManager;

/**
 * Applies the verified outcome of a payment to the payment, booking, and
 * opening records in one transaction. This is the single settlement path:
 * the Stripe webhook dispatcher calls it for real gateway events, and the
 * dev simulation endpoints call it to exercise the same transitions locally.
 */
final class SettlePaymentOutcomeService
{
    private const SETTLEABLE_PAYMENT_STATES = ['initiated', 'authorized'];

    public function __construct(
        private TransactionManager $tx,
        private PaymentRepository $payments,
        private BookingRepository $bookings,
        private OpeningRepository $openings,
    ) {
    }

    /** @return array<string, mixed> */
    public function succeed(string $paymentId): array
    {
        return $this->settle($paymentId, true, null);
    }

    /** @return array<string, mixed> */
    public function fail(string $paymentId, string $reason): array
    {
        return $this->settle($paymentId, false, $reason);
    }

    /** @return array<string, mixed>|null */
    public function succeedByStripeIntentId(string $intentId): ?array
    {
        $payment = $this->payments->findByStripeIntentId($intentId);

        return $payment === null ? null : $this->settle((string) $payment['id'], true, null);
    }

    /** @return array<string, mixed>|null */
    public function failByStripeIntentId(string $intentId, string $reason): ?array
    {
        $payment = $this->payments->findByStripeIntentId($intentId);

        return $payment === null ? null : $this->settle((string) $payment['id'], false, $reason);
    }

    /** @return array<string, mixed> */
    private function settle(string $paymentId, bool $succeeded, ?string $reason): array
    {
        return $this->tx->withinTransaction(function () use ($paymentId, $succeeded, $reason): array {
            $payment = $this->payments->findById($paymentId);
            if ($payment === null) {
                throw new ApiException(404, new ApiError('PAYMENT_NOT_FOUND', 'Payment was not found.'));
            }
            if (!in_array((string) $payment['state'], self::SETTLEABLE_PAYMENT_STATES, true)) {
                throw new ApiException(409, new ApiError('PAYMENT_STATE_INVALID', 'Payment outcome is already settled.'));
            }

            $booking = $this->bookings->lockById((string) $payment['booking_id']);
            if ($booking === null) {
                throw new ApiException(409, new ApiError('PAYMENT_BOOKING_MISSING', 'Payment refers to a missing booking.'));
            }

            if ($succeeded) {
                $this->payments->markCaptured($paymentId);
                $bookingDetail = $this->bookings->markConfirmed((string) $booking['id']);
                $this->openings->updateStatus((string) $booking['opening_id'], 'booked');
                $paymentState = 'captured';
            } else {
                $this->payments->markFailed($paymentId, $reason ?? 'payment_failed');
                $this->bookings->updateState((string) $booking['id'], 'payment_failed');
                // Failed payment releases the slot back to the public pool.
                $this->openings->updateStatus((string) $booking['opening_id'], 'published');
                $bookingDetail = $this->bookings->findDetailById((string) $booking['id']);
                assert($bookingDetail !== null);
                $paymentState = 'failed';
            }

            return [
                'payment_id' => $paymentId,
                'state' => $paymentState,
                'booking' => $bookingDetail,
            ];
        });
    }
}

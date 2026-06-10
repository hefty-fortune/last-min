<?php

declare(strict_types=1);

namespace App\Modules\Refunds\Application\Service;

use App\Modules\Payments\Application\Port\PaymentRepository;
use App\Modules\Refunds\Application\Port\RefundRepository;

final class RequestRefundService
{
    private const REFUNDABLE_PAYMENT_STATES = ['initiated', 'authorized', 'captured', 'succeeded'];

    public function __construct(
        private PaymentRepository $payments,
        private RefundRepository $refunds,
    ) {
    }

    /**
     * System-driven refund request, e.g. triggered by a provider no-show
     * transition. No-op when the booking has no refundable payment or an
     * active refund already exists.
     *
     * Idempotency relies on the caller: the only current caller
     * (MarkNoShowService) holds the booking row lock and a confirmed-state
     * guard, so this runs at most once per booking. The hasActiveRefundForPayment
     * check is a best-effort guard; the `uq_refunds_active_payment` partial unique
     * index is the hard backstop. A direct concurrent caller without the booking
     * lock could still surface that constraint violation — add a catch here if
     * such a path is introduced.
     *
     * @return array<string, mixed>|null
     */
    public function requestForBooking(string $bookingId, string $reason): ?array
    {
        $payment = $this->payments->findByBookingId($bookingId);
        if ($payment === null || !in_array($payment['state'], self::REFUNDABLE_PAYMENT_STATES, true)) {
            return null;
        }
        if ($this->refunds->hasActiveRefundForPayment((string) $payment['id'])) {
            return null;
        }

        return $this->refunds->createRequested([
            'payment_id' => $payment['id'],
            'booking_id' => $bookingId,
            'reason' => $reason,
            'amount' => (int) $payment['amount'],
            'currency' => $payment['currency'],
        ]);
    }
}

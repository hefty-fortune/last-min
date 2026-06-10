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
     * active refund already exists (retry-safe).
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

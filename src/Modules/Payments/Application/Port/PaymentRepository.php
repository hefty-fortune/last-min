<?php

declare(strict_types=1);

namespace App\Modules\Payments\Application\Port;

interface PaymentRepository
{
    public function findByBookingId(string $bookingId): ?array;

    public function createInitiated(array $payment): array;

    public function attachStripeIntent(string $paymentId, string $intentId): void;
}

<?php

declare(strict_types=1);

namespace App\Modules\Payments\Application\Port;

interface PaymentRepository
{
    public function findByBookingId(string $bookingId): ?array;

    /** @return array<string, mixed>|null */
    public function findById(string $paymentId): ?array;

    /** @return array<string, mixed>|null */
    public function findByStripeIntentId(string $intentId): ?array;

    public function createInitiated(array $payment): array;

    public function attachStripeIntent(string $paymentId, string $intentId): void;

    public function markCaptured(string $paymentId): void;

    public function markFailed(string $paymentId, string $reason): void;
}

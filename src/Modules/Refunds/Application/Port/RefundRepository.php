<?php

declare(strict_types=1);

namespace App\Modules\Refunds\Application\Port;

interface RefundRepository
{
    /** @return array<string, mixed>|null */
    public function findById(string $refundId): ?array;

    /** Locks the refund row for update (must be called within a transaction).
     *  @return array<string, mixed>|null
     */
    public function lockById(string $refundId): ?array;

    /** @return list<array<string, mixed>> */
    public function listByBookingId(string $bookingId): array;

    public function hasActiveRefundForPayment(string $paymentId): bool;

    /** @param array<string, mixed> $refund
     *  @return array<string, mixed>
     */
    public function createRequested(array $refund): array;

    /** @return array<string, mixed> */
    public function recordDecision(string $refundId, string $state, string $decidedByActorId, ?string $note): array;

    /** @return list<string> */
    public function listPendingIds(int $limit): array;

    /** @return array<string, mixed> */
    public function markSucceeded(string $refundId, string $stripeRefundId): array;

    /** @return array<string, mixed> */
    public function markFailed(string $refundId, string $reason): array;
}

<?php

declare(strict_types=1);

namespace App\Modules\Booking\Application\Port;

interface BookingRepository
{
    public function hasActiveBookingForOpening(string $openingId): bool;

    public function createReserved(array $booking): array;

    public function findById(string $bookingId): ?array;

    /** @return array<string, mixed>|null */
    public function findDetailById(string $bookingId): ?array;

    /** @return array<string, mixed>|null */
    public function lockById(string $bookingId): ?array;

    /** @return array<string, mixed> */
    public function markNoShow(string $bookingId, string $noShowActor, string $state): array;

    /** @return array<string, mixed> */
    public function markConfirmed(string $bookingId): array;

    public function updateState(string $bookingId, string $state): void;

    /** @return list<array<string, mixed>> */
    public function listByClientProfileId(string $clientProfileId, ?string $state, int $limit): array;

    /** @return list<string> */
    public function listExpiredReservedIds(string $nowIso, int $limit): array;

    /** @return list<array<string, mixed>> */
    public function listByProviderId(string $providerId, ?string $state, int $limit): array;

    public function countByOpeningId(string $openingId): int;
}

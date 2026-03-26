<?php

declare(strict_types=1);

namespace App\Modules\Booking\Application\Port;

interface BookingRepository
{
    public function hasActiveBookingForOpening(string $openingId): bool;

    public function createReserved(array $booking): array;

    public function findById(string $bookingId): ?array;
}

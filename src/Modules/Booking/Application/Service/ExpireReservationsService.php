<?php

declare(strict_types=1);

namespace App\Modules\Booking\Application\Service;

use App\Modules\Booking\Application\Port\BookingRepository;
use App\Modules\Openings\Application\Port\OpeningRepository;
use App\Platform\Audit\AuditLogger;
use App\Platform\Persistence\TransactionManager;

/**
 * Sweep job: reservations that passed their hold deadline without a
 * successful payment stop blocking the slot. Run periodically by the
 * worker (bin/run-worker.php).
 */
final class ExpireReservationsService
{
    public function __construct(
        private TransactionManager $tx,
        private BookingRepository $bookings,
        private OpeningRepository $openings,
        private AuditLogger $audit,
    ) {
    }

    /** @return array{expired: int} */
    public function expireDue(int $batchSize = 100): array
    {
        $now = (new \DateTimeImmutable())->format(DATE_ATOM);
        $expired = 0;

        foreach ($this->bookings->listExpiredReservedIds($now, $batchSize) as $bookingId) {
            $expired += $this->expireOne((string) $bookingId, $now) ? 1 : 0;
        }

        return ['expired' => $expired];
    }

    private function expireOne(string $bookingId, string $nowIso): bool
    {
        return $this->tx->withinTransaction(function () use ($bookingId, $nowIso): bool {
            $booking = $this->bookings->lockById($bookingId);
            // Re-check under lock: a concurrent payment may have settled it.
            if ($booking === null || $booking['state'] !== 'reserved') {
                return false;
            }
            if ($booking['reservation_expires_at'] === null || $booking['reservation_expires_at'] > $nowIso) {
                return false;
            }

            $this->bookings->updateState($bookingId, 'reservation_expired');
            // Release the slot back to the public pool.
            $this->openings->updateStatus((string) $booking['opening_id'], 'published');
            $this->audit->record(null, 'booking.reservation-expired', 'booking', $bookingId, [
                'opening_id' => $booking['opening_id'],
            ]);

            return true;
        });
    }
}

<?php

declare(strict_types=1);

namespace App\Modules\Booking\Infrastructure\Persistence;

use App\Modules\Booking\Application\Port\BookingRepository;
use PDO;

final class PdoBookingRepository implements BookingRepository
{
    public function __construct(private PDO $pdo)
    {
    }

    public function hasActiveBookingForOpening(string $openingId): bool
    {
        $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM bookings WHERE opening_id=:opening_id AND state IN ('reserved','payment_pending','confirmed')");
        $stmt->execute(['opening_id' => $openingId]);
        return (int) $stmt->fetchColumn() > 0;
    }

    public function createReserved(array $booking): array
    {
        $id = self::uuid();
        $now = new \DateTimeImmutable();
        $expiresAt = $now->modify('+10 minutes');

        $stmt = $this->pdo->prepare('INSERT INTO bookings (id, opening_id, provider_id, client_user_profile_id, state, reservation_expires_at, payment_required_amount, payment_currency, created_at, updated_at) VALUES (:id, :opening_id, :provider_id, :client_profile, :state, :expires, :amount, :currency, :created, :updated)');
        $stmt->execute([
            'id' => $id,
            'opening_id' => $booking['opening_id'],
            'provider_id' => $booking['provider_id'],
            'client_profile' => $booking['client_user_profile_id'],
            'state' => 'reserved',
            'expires' => $expiresAt->format(DATE_ATOM),
            'amount' => $booking['payment_required_amount'],
            'currency' => $booking['payment_currency'],
            'created' => $now->format(DATE_ATOM),
            'updated' => $now->format(DATE_ATOM),
        ]);

        return [
            'booking_id' => $id,
            'opening_id' => $booking['opening_id'],
            'state' => 'reserved',
            'reserved_at' => $now->format(DATE_ATOM),
            'expires_at' => $expiresAt->format(DATE_ATOM),
        ];
    }

    public function findById(string $bookingId): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM bookings WHERE id=:id LIMIT 1');
        $stmt->execute(['id' => $bookingId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row === false ? null : $row;
    }

    private static function uuid(): string
    {
        $data = random_bytes(16);
        $data[6] = chr((ord($data[6]) & 0x0f) | 0x40);
        $data[8] = chr((ord($data[8]) & 0x3f) | 0x80);
        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
    }
}

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

    public function findDetailById(string $bookingId): ?array
    {
        $row = $this->findById($bookingId);
        return $row === null ? null : $this->mapBooking($row);
    }

    public function lockById(string $bookingId): ?array
    {
        $sql = 'SELECT * FROM bookings WHERE id = :id';
        if ($this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME) !== 'sqlite') {
            $sql .= ' FOR UPDATE';
        }
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(['id' => $bookingId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row === false ? null : $row;
    }

    public function markNoShow(string $bookingId, string $noShowActor, string $state): array
    {
        $now = (new \DateTimeImmutable())->format(DATE_ATOM);
        $stmt = $this->pdo->prepare('UPDATE bookings SET state = :state, no_show_actor = :no_show_actor, no_show_recorded_at = :recorded_at, updated_at = :updated_at WHERE id = :id');
        $stmt->execute([
            'id' => $bookingId,
            'state' => $state,
            'no_show_actor' => $noShowActor,
            'recorded_at' => $now,
            'updated_at' => $now,
        ]);

        $detail = $this->findDetailById($bookingId);
        assert($detail !== null);

        return $detail;
    }

    public function listByClientProfileId(string $clientProfileId, ?string $state, int $limit): array
    {
        $safeLimit = max(1, min($limit, 100));
        $sql = 'SELECT * FROM bookings WHERE client_user_profile_id = :client_profile';
        $params = ['client_profile' => $clientProfileId];

        if ($state !== null && trim($state) !== '') {
            $sql .= ' AND state = :state';
            $params['state'] = $state;
        }

        $sql .= ' ORDER BY created_at DESC LIMIT :limit';
        $stmt = $this->pdo->prepare($sql);
        foreach ($params as $key => $value) {
            $stmt->bindValue(':' . $key, $value);
        }
        $stmt->bindValue(':limit', $safeLimit, PDO::PARAM_INT);
        $stmt->execute();

        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return array_map(fn (array $row): array => $this->mapBooking($row), $rows);
    }

    /** @param array<string, mixed> $row
     *  @return array<string, mixed>
     */
    private function mapBooking(array $row): array
    {
        return [
            'booking_id' => $row['id'],
            'opening_id' => $row['opening_id'],
            'provider_id' => $row['provider_id'],
            'client_user_profile_id' => $row['client_user_profile_id'],
            'state' => $row['state'],
            'reserved_at' => $row['created_at'],
            'expires_at' => $row['reservation_expires_at'],
            'amount' => ['currency' => $row['payment_currency'], 'amount_minor' => (int) $row['payment_required_amount']],
            'no_show_actor' => $row['no_show_actor'],
            'no_show_recorded_at' => $row['no_show_recorded_at'],
            'confirmed_at' => $row['confirmed_at'],
            'completed_at' => $row['completed_at'],
            'created_at' => $row['created_at'],
            'updated_at' => $row['updated_at'],
        ];
    }

    private static function uuid(): string
    {
        $data = random_bytes(16);
        $data[6] = chr((ord($data[6]) & 0x0f) | 0x40);
        $data[8] = chr((ord($data[8]) & 0x3f) | 0x80);
        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
    }
}

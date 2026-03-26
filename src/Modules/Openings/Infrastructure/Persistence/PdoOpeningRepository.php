<?php

declare(strict_types=1);

namespace App\Modules\Openings\Infrastructure\Persistence;

use App\Modules\Openings\Application\Port\OpeningRepository;
use PDO;

final class PdoOpeningRepository implements OpeningRepository
{
    public function __construct(private PDO $pdo)
    {
    }

    public function createDraft(array $data): array
    {
        $id = self::uuid();
        $now = (new \DateTimeImmutable())->format(DATE_ATOM);
        $stmt = $this->pdo->prepare('INSERT INTO openings (id, provider_id, service_offering_id, starts_at, ends_at, timezone, capacity, status, published_at, created_at, updated_at, price_amount, price_currency) VALUES (:id, :provider_id, :offering_id, :starts_at, :ends_at, :timezone, :capacity, :status, NULL, :created_at, :updated_at, :price_amount, :price_currency)');
        $stmt->execute([
            'id' => $id,
            'provider_id' => $data['provider_id'],
            'offering_id' => $data['service_offering_id'],
            'starts_at' => $data['starts_at'],
            'ends_at' => $data['ends_at'],
            'timezone' => 'UTC',
            'capacity' => 1,
            'status' => 'draft',
            'created_at' => $now,
            'updated_at' => $now,
            'price_amount' => $data['price_amount'],
            'price_currency' => $data['price_currency'],
        ]);

        return [
            'opening_id' => $id,
            'provider_id' => $data['provider_id'],
            'service_offering_id' => $data['service_offering_id'],
            'starts_at' => $data['starts_at'],
            'ends_at' => $data['ends_at'],
            'status' => 'draft',
        ];
    }

    public function lockById(string $openingId): ?array
    {
        $sql = 'SELECT * FROM openings WHERE id = :id';
        if ($this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME) !== 'sqlite') {
            $sql .= ' FOR UPDATE';
        }
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(['id' => $openingId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row === false ? null : $row;
    }

    public function updateStatus(string $openingId, string $status): void
    {
        $stmt = $this->pdo->prepare('UPDATE openings SET status=:status, updated_at=:updated_at WHERE id=:id');
        $stmt->execute(['id' => $openingId, 'status' => $status, 'updated_at' => (new \DateTimeImmutable())->format(DATE_ATOM)]);
    }

    private static function uuid(): string
    {
        $data = random_bytes(16);
        $data[6] = chr((ord($data[6]) & 0x0f) | 0x40);
        $data[8] = chr((ord($data[8]) & 0x3f) | 0x80);
        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
    }
}

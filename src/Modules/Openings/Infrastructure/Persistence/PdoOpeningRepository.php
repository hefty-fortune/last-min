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

    public function serviceOfferingBelongsToProvider(string $serviceOfferingId, string $providerId): bool
    {
        $stmt = $this->pdo->prepare('SELECT 1 FROM service_offerings WHERE id = :id AND provider_id = :provider_id LIMIT 1');
        $stmt->execute(['id' => $serviceOfferingId, 'provider_id' => $providerId]);

        return $stmt->fetchColumn() !== false;
    }

    public function getServiceOfferingPrice(string $serviceOfferingId, string $providerId): ?array
    {
        $stmt = $this->pdo->prepare('SELECT price_amount, price_currency FROM service_offerings WHERE id = :id AND provider_id = :provider_id LIMIT 1');
        $stmt->execute(['id' => $serviceOfferingId, 'provider_id' => $providerId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row === false ? null : ['amount_minor' => (int) $row['price_amount'], 'currency' => (string) $row['price_currency']];
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

        return $this->mustFindById($id);
    }

    public function findById(string $openingId): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM openings WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $openingId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row === false ? null : $this->mapOpening($row);
    }

    public function findByProviderIdAndId(string $providerId, string $openingId): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM openings WHERE provider_id = :provider_id AND id = :id LIMIT 1');
        $stmt->execute(['provider_id' => $providerId, 'id' => $openingId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row === false ? null : $this->mapOpening($row);
    }

    public function listByProviderId(string $providerId, ?string $status, int $limit): array
    {
        $safeLimit = max(1, min($limit, 100));

        if ($status !== null && trim($status) !== '') {
            $stmt = $this->pdo->prepare('SELECT * FROM openings WHERE provider_id = :provider_id AND status = :status ORDER BY starts_at ASC LIMIT :limit');
            $stmt->bindValue(':provider_id', $providerId);
            $stmt->bindValue(':status', $status);
            $stmt->bindValue(':limit', $safeLimit, PDO::PARAM_INT);
            $stmt->execute();
        } else {
            $stmt = $this->pdo->prepare('SELECT * FROM openings WHERE provider_id = :provider_id ORDER BY starts_at ASC LIMIT :limit');
            $stmt->bindValue(':provider_id', $providerId);
            $stmt->bindValue(':limit', $safeLimit, PDO::PARAM_INT);
            $stmt->execute();
        }

        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return array_map(fn (array $row): array => $this->mapOpening($row), $rows);
    }

    public function listPublished(array $filters, int $limit): array
    {
        $safeLimit = max(1, min($limit, 100));
        $conditions = ['o.status = :status'];
        $params = ['status' => 'published'];

        if (isset($filters['provider_id']) && trim((string) $filters['provider_id']) !== '') {
            $conditions[] = 'o.provider_id = :provider_id';
            $params['provider_id'] = (string) $filters['provider_id'];
        }

        if (isset($filters['service_offering_id']) && trim((string) $filters['service_offering_id']) !== '') {
            $conditions[] = 'o.service_offering_id = :service_offering_id';
            $params['service_offering_id'] = (string) $filters['service_offering_id'];
        }

        if (isset($filters['starts_after']) && trim((string) $filters['starts_after']) !== '') {
            $conditions[] = 'o.starts_at >= :starts_after';
            $params['starts_after'] = (string) $filters['starts_after'];
        }

        if (isset($filters['starts_before']) && trim((string) $filters['starts_before']) !== '') {
            $conditions[] = 'o.starts_at <= :starts_before';
            $params['starts_before'] = (string) $filters['starts_before'];
        }

        if (isset($filters['max_price_minor']) && $filters['max_price_minor'] !== '') {
            $conditions[] = 'o.price_amount <= :max_price_minor';
            $params['max_price_minor'] = (int) $filters['max_price_minor'];
        }

        $stmt = $this->pdo->prepare(sprintf(
            'SELECT o.*, p.display_name AS provider_display_name, s.name AS offering_name, s.duration_minutes AS offering_duration_minutes
             FROM openings o
             LEFT JOIN providers p ON p.id = o.provider_id
             LEFT JOIN service_offerings s ON s.id = o.service_offering_id
             WHERE %s ORDER BY o.starts_at ASC LIMIT :limit',
            implode(' AND ', $conditions)
        ));

        foreach ($params as $key => $value) {
            $stmt->bindValue(
                ':' . $key,
                $value,
                is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR,
            );
        }
        $stmt->bindValue(':limit', $safeLimit, PDO::PARAM_INT);
        $stmt->execute();

        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return array_map(function (array $row): array {
            $opening = $this->mapOpening($row);
            // Public storefront projection: human-readable context for the card.
            $opening['provider_display_name'] = isset($row['provider_display_name']) ? (string) $row['provider_display_name'] : null;
            $opening['offering_name'] = isset($row['offering_name']) ? (string) $row['offering_name'] : null;
            $opening['offering_duration_minutes'] = isset($row['offering_duration_minutes']) ? (int) $row['offering_duration_minutes'] : null;

            return $opening;
        }, $rows);
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

    public function publish(string $openingId): array
    {
        $publishedAt = (new \DateTimeImmutable())->format(DATE_ATOM);
        $stmt = $this->pdo->prepare('UPDATE openings SET status = :status, published_at = :published_at, updated_at = :updated_at WHERE id = :id');
        $stmt->execute([
            'id' => $openingId,
            'status' => 'published',
            'published_at' => $publishedAt,
            'updated_at' => $publishedAt,
        ]);

        return $this->mustFindById($openingId);
    }

    public function cancel(string $openingId): array
    {
        $updatedAt = (new \DateTimeImmutable())->format(DATE_ATOM);
        $stmt = $this->pdo->prepare('UPDATE openings SET status = :status, cancelled_at = :cancelled_at, updated_at = :updated_at WHERE id = :id');
        $stmt->execute([
            'id' => $openingId,
            'status' => 'cancelled_by_provider',
            'cancelled_at' => $updatedAt,
            'updated_at' => $updatedAt,
        ]);

        return $this->mustFindById($openingId);
    }

    /** @param array<string, mixed> $row */
    private function mapOpening(array $row): array
    {
        return [
            'opening_id' => (string) $row['id'],
            'provider_id' => (string) $row['provider_id'],
            'service_offering_id' => (string) $row['service_offering_id'],
            'starts_at' => (string) $row['starts_at'],
            'ends_at' => (string) $row['ends_at'],
            'timezone' => (string) $row['timezone'],
            'capacity' => (int) $row['capacity'],
            'status' => (string) $row['status'],
            'published_at' => $row['published_at'] !== null ? (string) $row['published_at'] : null,
            'cancelled_at' => isset($row['cancelled_at']) && $row['cancelled_at'] !== null ? (string) $row['cancelled_at'] : null,
            'created_at' => isset($row['created_at']) ? (string) $row['created_at'] : null,
            'updated_at' => isset($row['updated_at']) ? (string) $row['updated_at'] : null,
            'price_snapshot' => [
                'currency' => (string) $row['price_currency'],
                'amount_minor' => (int) $row['price_amount'],
            ],
        ];
    }

    private function mustFindById(string $openingId): array
    {
        $opening = $this->findById($openingId);
        if ($opening === null) {
            throw new \RuntimeException('Opening row was not found after persistence write.');
        }

        return $opening;
    }

    private static function uuid(): string
    {
        $data = random_bytes(16);
        $data[6] = chr((ord($data[6]) & 0x0f) | 0x40);
        $data[8] = chr((ord($data[8]) & 0x3f) | 0x80);
        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
    }
}

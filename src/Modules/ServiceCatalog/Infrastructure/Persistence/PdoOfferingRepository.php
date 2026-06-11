<?php

declare(strict_types=1);

namespace App\Modules\ServiceCatalog\Infrastructure\Persistence;

use App\Modules\ServiceCatalog\Application\Port\OfferingRepository;
use PDO;

final class PdoOfferingRepository implements OfferingRepository
{
    public function __construct(private PDO $pdo)
    {
    }

    public function findByProviderIdAndId(string $providerId, string $offeringId): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM service_offerings WHERE id = :id AND provider_id = :provider_id LIMIT 1');
        $stmt->execute(['id' => $offeringId, 'provider_id' => $providerId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row === false ? null : $this->mapOffering($row);
    }

    public function listByProviderId(string $providerId, ?string $status, int $limit): array
    {
        $safeLimit = max(1, min($limit, 100));
        $sql = 'SELECT * FROM service_offerings WHERE provider_id = :provider_id';
        $params = ['provider_id' => $providerId];

        if ($status !== null && trim($status) !== '') {
            $sql .= ' AND status = :status';
            $params['status'] = $status;
        }

        $sql .= ' ORDER BY created_at DESC LIMIT :limit';
        $stmt = $this->pdo->prepare($sql);
        foreach ($params as $key => $value) {
            $stmt->bindValue(':' . $key, $value);
        }
        $stmt->bindValue(':limit', $safeLimit, PDO::PARAM_INT);
        $stmt->execute();

        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return array_map(fn (array $row): array => $this->mapOffering($row), $rows);
    }

    public function create(array $offering): array
    {
        $id = self::uuid();
        $now = (new \DateTimeImmutable())->format(DATE_ATOM);
        $stmt = $this->pdo->prepare('INSERT INTO service_offerings (id, provider_id, name, description, duration_minutes, price_amount, price_currency, status, created_at, updated_at) VALUES (:id, :provider_id, :name, :description, :duration_minutes, :price_amount, :price_currency, :status, :created_at, :updated_at)');
        $stmt->execute([
            'id' => $id,
            'provider_id' => $offering['provider_id'],
            'name' => $offering['name'],
            'description' => $offering['description'],
            'duration_minutes' => $offering['duration_minutes'],
            'price_amount' => $offering['price_amount'],
            'price_currency' => $offering['price_currency'],
            'status' => 'active',
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $created = $this->findByProviderIdAndId((string) $offering['provider_id'], $id);
        assert($created !== null);

        return $created;
    }

    /** Columns this repository will write via update(); guards against a caller
     *  ever passing an attacker-controlled key into the dynamic SET clause. */
    private const UPDATABLE_COLUMNS = ['name', 'description', 'duration_minutes', 'price_amount', 'price_currency', 'status'];

    public function update(string $offeringId, array $changes): array
    {
        $columns = [];
        $params = ['id' => $offeringId, 'updated_at' => (new \DateTimeImmutable())->format(DATE_ATOM)];
        foreach ($changes as $column => $value) {
            if (!in_array($column, self::UPDATABLE_COLUMNS, true)) {
                throw new \InvalidArgumentException(sprintf('Column "%s" is not updatable.', $column));
            }
            $columns[] = "$column = :$column";
            $params[$column] = $value;
        }
        $columns[] = 'updated_at = :updated_at';

        $stmt = $this->pdo->prepare(sprintf('UPDATE service_offerings SET %s WHERE id = :id', implode(', ', $columns)));
        $stmt->execute($params);

        $stmt = $this->pdo->prepare('SELECT * FROM service_offerings WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $offeringId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        assert($row !== false);

        return $this->mapOffering($row);
    }

    public function delete(string $offeringId): void
    {
        $this->pdo->prepare('DELETE FROM service_offerings WHERE id = :id')->execute(['id' => $offeringId]);
    }

    /** @param array<string, mixed> $row
     *  @return array<string, mixed>
     */
    private function mapOffering(array $row): array
    {
        return [
            'offering_id' => $row['id'],
            'provider_id' => $row['provider_id'],
            'name' => $row['name'],
            'description' => $row['description'],
            'duration_minutes' => (int) $row['duration_minutes'],
            'base_price' => ['currency' => $row['price_currency'], 'amount_minor' => (int) $row['price_amount']],
            'status' => $row['status'],
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

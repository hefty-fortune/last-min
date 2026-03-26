<?php

declare(strict_types=1);

namespace App\Modules\Providers\Infrastructure\Persistence;

use App\Modules\Providers\Application\Port\ProviderRepository;
use PDO;

final class PdoProviderRepository implements ProviderRepository
{
    public function __construct(private PDO $pdo)
    {
    }

    public function createIndividual(string $ownerUserProfileId): array
    {
        $id = self::uuid();
        $now = (new \DateTimeImmutable())->format(DATE_ATOM);
        $stmt = $this->pdo->prepare('INSERT INTO providers (id, provider_type, owner_user_profile_id, organization_id, status, created_at, updated_at) VALUES (:id, :type, :owner, NULL, :status, :created, :updated)');
        $stmt->execute(['id' => $id, 'type' => 'individual', 'owner' => $ownerUserProfileId, 'status' => 'active', 'created' => $now, 'updated' => $now]);

        return ['provider_id' => $id, 'provider_type' => 'individual', 'status' => 'active'];
    }

    public function findById(string $providerId): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM providers WHERE id=:id LIMIT 1');
        $stmt->execute(['id' => $providerId]);
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

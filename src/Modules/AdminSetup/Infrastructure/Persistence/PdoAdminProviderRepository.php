<?php

declare(strict_types=1);

namespace App\Modules\AdminSetup\Infrastructure\Persistence;

use App\Modules\AdminSetup\Application\Port\AdminProviderRepository;
use PDO;

final class PdoAdminProviderRepository implements AdminProviderRepository
{
    public function __construct(private PDO $pdo)
    {
    }

    public function create(array $provider): array
    {
        $id = self::uuid();
        $now = (new \DateTimeImmutable())->format(DATE_ATOM);
        $stmt = $this->pdo->prepare('INSERT INTO providers (id, provider_type, owner_user_profile_id, organization_id, display_name, status, created_at, updated_at) VALUES (:id, :provider_type, NULL, :organization_id, :display_name, :status, :created_at, :updated_at)');
        $stmt->execute([
            'id' => $id,
            'provider_type' => 'organization',
            'organization_id' => $provider['organization_id'],
            'display_name' => $provider['display_name'],
            'status' => $provider['status'],
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        return [
            'provider_id' => $id,
            'organization_id' => $provider['organization_id'],
            'display_name' => $provider['display_name'],
            'provider_type' => 'organization',
            'status' => $provider['status'],
        ];
    }

    public function existsById(string $providerId): bool
    {
        $stmt = $this->pdo->prepare('SELECT 1 FROM providers WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $providerId]);

        return $stmt->fetchColumn() !== false;
    }

    private static function uuid(): string
    {
        $data = random_bytes(16);
        $data[6] = chr((ord($data[6]) & 0x0f) | 0x40);
        $data[8] = chr((ord($data[8]) & 0x3f) | 0x80);
        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
    }
}

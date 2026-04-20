<?php

declare(strict_types=1);

namespace App\Modules\IdentityAccess\Infrastructure\Persistence;

use App\Modules\IdentityAccess\Application\Port\ApiKeyRepository;
use PDO;

final class PdoApiKeyRepository implements ApiKeyRepository
{
    public function __construct(private PDO $pdo)
    {
    }

    public function create(string $clientId, string $name, string $plainApiKey): array
    {
        $id = self::uuid();
        $now = (new \DateTimeImmutable())->format(DATE_ATOM);
        $stmt = $this->pdo->prepare('INSERT INTO api_keys (id, client_id, key_name, key_hash, key_prefix, created_at, revoked_at) VALUES (:id, :client_id, :key_name, :key_hash, :key_prefix, :created_at, NULL)');
        $stmt->execute([
            'id' => $id,
            'client_id' => $clientId,
            'key_name' => $name,
            'key_hash' => hash('sha256', $plainApiKey),
            'key_prefix' => substr($plainApiKey, 0, 10),
            'created_at' => $now,
        ]);

        return [
            'api_key_id' => $id,
            'client_id' => $clientId,
            'name' => $name,
        ];
    }

    public function revokeByClientId(string $clientId): bool
    {
        $stmt = $this->pdo->prepare('UPDATE api_keys SET revoked_at = :revoked_at WHERE client_id = :client_id AND revoked_at IS NULL');
        $stmt->execute(['revoked_at' => (new \DateTimeImmutable())->format(DATE_ATOM), 'client_id' => $clientId]);

        return $stmt->rowCount() > 0;
    }

    public function findActiveByTokenHash(string $tokenHash): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM api_keys WHERE key_hash = :key_hash AND revoked_at IS NULL LIMIT 1');
        $stmt->execute(['key_hash' => $tokenHash]);
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

<?php

declare(strict_types=1);

namespace App\Modules\IdentityAccess\Infrastructure\Persistence;

use App\Common\Api\ApiError;
use App\Common\Api\ApiException;
use App\Modules\IdentityAccess\Application\Port\ApiKeyRepository;
use PDO;
use PDOException;

final class PdoApiKeyRepository implements ApiKeyRepository
{
    public function __construct(private PDO $pdo)
    {
    }

    public function createForActor(string $actorType, string $actorId, array $roles, string $name, string $plainApiKey): array
    {
        if ($roles === []) {
            $roles = [$actorType];
        }

        $id = self::uuid();
        $now = (new \DateTimeImmutable())->format(DATE_ATOM);
        $clientId = 'actor:' . $actorType . ':' . $actorId;
        try {
            $stmt = $this->pdo->prepare('INSERT INTO api_keys (id, client_id, actor_type, actor_id, actor_roles, key_name, key_hash, key_prefix, created_at, revoked_at) VALUES (:id, :client_id, :actor_type, :actor_id, :actor_roles, :key_name, :key_hash, :key_prefix, :created_at, NULL)');
            $stmt->execute([
                'id' => $id,
                'client_id' => $clientId,
                'actor_type' => $actorType,
                'actor_id' => $actorId,
                'actor_roles' => json_encode(array_values(array_unique($roles)), JSON_THROW_ON_ERROR),
                'key_name' => $name,
                'key_hash' => hash('sha256', $plainApiKey),
                'key_prefix' => substr($plainApiKey, 0, 10),
                'created_at' => $now,
            ]);
        } catch (PDOException $e) {
            if ($this->isUniqueViolation($e, 'api_keys.client_id, api_keys.key_name')) {
                throw new ApiException(409, new ApiError('CONFLICT_API_KEY_NAME_EXISTS', 'An active API key with this name already exists.'));
            }

            throw $e;
        }

        return [
            'api_key_id' => $id,
            'actor_type' => $actorType,
            'actor_id' => $actorId,
            'roles' => array_values(array_unique($roles)),
            'name' => $name,
        ];
    }

    public function revokeByApiKeyId(string $apiKeyId): bool
    {
        $stmt = $this->pdo->prepare('UPDATE api_keys SET revoked_at = :revoked_at WHERE id = :id AND revoked_at IS NULL');
        $stmt->execute(['revoked_at' => (new \DateTimeImmutable())->format(DATE_ATOM), 'id' => $apiKeyId]);

        return $stmt->rowCount() > 0;
    }

    public function listAll(): array
    {
        $stmt = $this->pdo->query('SELECT id, key_name, key_prefix, created_at, revoked_at FROM api_keys ORDER BY created_at ASC');
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return array_map(static fn (array $row): array => [
            'api_key_id' => (string) $row['id'],
            'name' => (string) $row['key_name'],
            'key_prefix' => (string) $row['key_prefix'],
            'created_at' => (string) $row['created_at'],
            'revoked_at' => $row['revoked_at'] !== null ? (string) $row['revoked_at'] : null,
            'is_active' => $row['revoked_at'] === null,
        ], $rows);
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

    private function isUniqueViolation(PDOException $e, string $needle): bool
    {
        if (($e->errorInfo[0] ?? null) !== '23000') {
            return false;
        }

        return str_contains(strtolower($e->getMessage()), strtolower($needle));
    }
}

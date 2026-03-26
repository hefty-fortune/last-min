<?php

declare(strict_types=1);

namespace App\Platform\Idempotency;

use PDO;

final class PdoIdempotencyStore implements IdempotencyStore
{
    public function __construct(private PDO $pdo)
    {
    }

    public function find(string $scope, string $key): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM idempotency_keys WHERE scope = :scope AND idempotency_key = :key LIMIT 1');
        $stmt->execute(['scope' => $scope, 'key' => $key]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row === false ? null : $row;
    }

    public function reserve(string $scope, string $key, string $requestHash, \DateTimeImmutable $expiresAt): void
    {
        $stmt = $this->pdo->prepare('INSERT INTO idempotency_keys (id, scope, idempotency_key, request_hash, expires_at, created_at, updated_at) VALUES (:id, :scope, :key, :hash, :expires, :created, :updated)');
        $now = (new \DateTimeImmutable())->format(DATE_ATOM);
        $stmt->execute([
            'id' => self::uuid(),
            'scope' => $scope,
            'key' => $key,
            'hash' => $requestHash,
            'expires' => $expiresAt->format(DATE_ATOM),
            'created' => $now,
            'updated' => $now,
        ]);
    }

    public function storeResponse(string $scope, string $key, int $responseCode, array $responseBody, ?string $resourceType = null, ?string $resourceId = null): void
    {
        $stmt = $this->pdo->prepare('UPDATE idempotency_keys SET response_code=:code, response_body=:body, resource_type=:resource_type, resource_id=:resource_id, updated_at=:updated WHERE scope=:scope AND idempotency_key=:key');
        $stmt->execute([
            'code' => $responseCode,
            'body' => json_encode($responseBody, JSON_THROW_ON_ERROR),
            'resource_type' => $resourceType,
            'resource_id' => $resourceId,
            'updated' => (new \DateTimeImmutable())->format(DATE_ATOM),
            'scope' => $scope,
            'key' => $key,
        ]);
    }

    private static function uuid(): string
    {
        $data = random_bytes(16);
        $data[6] = chr((ord($data[6]) & 0x0f) | 0x40);
        $data[8] = chr((ord($data[8]) & 0x3f) | 0x80);
        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
    }
}

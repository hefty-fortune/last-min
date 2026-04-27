<?php

declare(strict_types=1);

namespace App\Modules\IdentityAccess\Infrastructure\Persistence;

use App\Modules\IdentityAccess\Application\Port\AuthSessionRepository;
use PDO;

final class PdoAuthSessionRepository implements AuthSessionRepository
{
    public function __construct(private PDO $pdo)
    {
    }

    public function createSession(string $userId, string $plainToken, \DateTimeImmutable $expiresAt): array
    {
        $id = self::uuid();
        $now = (new \DateTimeImmutable())->format(DATE_ATOM);

        $stmt = $this->pdo->prepare('INSERT INTO auth_sessions (id, user_id, token_hash, expires_at, created_at, revoked_at) VALUES (:id, :user_id, :token_hash, :expires_at, :created_at, NULL)');
        $stmt->execute([
            'id' => $id,
            'user_id' => $userId,
            'token_hash' => hash('sha256', $plainToken),
            'expires_at' => $expiresAt->format(DATE_ATOM),
            'created_at' => $now,
        ]);

        return ['session_id' => $id, 'user_id' => $userId, 'expires_at' => $expiresAt->format(DATE_ATOM)];
    }

    public function findActiveByTokenHash(string $tokenHash): ?array
    {
        $stmt = $this->pdo->prepare('SELECT s.*, u.first_name, u.last_name, u.email, u.provider_id FROM auth_sessions s JOIN users u ON u.id = s.user_id WHERE s.token_hash = :token_hash AND s.revoked_at IS NULL LIMIT 1');
        $stmt->execute(['token_hash' => $tokenHash]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($row === false) {
            return null;
        }

        $now = new \DateTimeImmutable();
        $expiresAt = new \DateTimeImmutable((string) $row['expires_at']);
        if ($expiresAt < $now) {
            return null;
        }

        return $row;
    }

    public function revokeByTokenHash(string $tokenHash): bool
    {
        $now = (new \DateTimeImmutable())->format(DATE_ATOM);
        $stmt = $this->pdo->prepare('UPDATE auth_sessions SET revoked_at = :revoked_at WHERE token_hash = :token_hash AND revoked_at IS NULL');
        $stmt->execute(['revoked_at' => $now, 'token_hash' => $tokenHash]);

        return $stmt->rowCount() > 0;
    }

    private static function uuid(): string
    {
        $data = random_bytes(16);
        $data[6] = chr((ord($data[6]) & 0x0f) | 0x40);
        $data[8] = chr((ord($data[8]) & 0x3f) | 0x80);
        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
    }
}

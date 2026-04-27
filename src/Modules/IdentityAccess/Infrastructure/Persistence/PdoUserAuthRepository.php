<?php

declare(strict_types=1);

namespace App\Modules\IdentityAccess\Infrastructure\Persistence;

use App\Modules\IdentityAccess\Application\Port\UserAuthRepository;
use PDO;

final class PdoUserAuthRepository implements UserAuthRepository
{
    public function __construct(private PDO $pdo)
    {
    }

    public function findByEmail(string $email): ?array
    {
        $stmt = $this->pdo->prepare('SELECT id, provider_id, first_name, last_name, email, phone, password_hash, status FROM users WHERE email = :email LIMIT 1');
        $stmt->execute(['email' => $email]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row === false ? null : $row;
    }

    public function findByEmailWithRoles(string $email): ?array
    {
        $user = $this->findByEmail($email);
        if ($user === null) {
            return null;
        }

        $roleStmt = $this->pdo->prepare('SELECT role_code FROM user_roles WHERE user_id = :user_id ORDER BY role_code ASC');
        $roleStmt->execute(['user_id' => $user['id']]);
        $user['roles'] = array_map(static fn(array $r): string => (string) $r['role_code'], $roleStmt->fetchAll(PDO::FETCH_ASSOC));

        return $user;
    }

    public function setPasswordHash(string $userId, string $passwordHash): void
    {
        $now = (new \DateTimeImmutable())->format(DATE_ATOM);
        $stmt = $this->pdo->prepare('UPDATE users SET password_hash = :password_hash, updated_at = :updated_at WHERE id = :id');
        $stmt->execute(['password_hash' => $passwordHash, 'updated_at' => $now, 'id' => $userId]);
    }
}

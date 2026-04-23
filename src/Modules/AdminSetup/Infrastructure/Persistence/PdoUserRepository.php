<?php

declare(strict_types=1);

namespace App\Modules\AdminSetup\Infrastructure\Persistence;

use App\Common\Api\ApiError;
use App\Common\Api\ApiException;
use App\Modules\AdminSetup\Application\Port\UserRepository;
use PDO;
use PDOException;

final class PdoUserRepository implements UserRepository
{
    public function __construct(private PDO $pdo)
    {
    }

    public function create(array $user, array $roles): array
    {
        $id = self::uuid();
        $now = (new \DateTimeImmutable())->format(DATE_ATOM);

        $this->pdo->beginTransaction();

        try {
            $insertUser = $this->pdo->prepare('INSERT INTO users (id, provider_id, first_name, last_name, email, phone, password_hash, status, created_at, updated_at) VALUES (:id, :provider_id, :first_name, :last_name, :email, :phone, :password_hash, :status, :created_at, :updated_at)');
            $insertUser->execute([
                'id' => $id,
                'provider_id' => $user['provider_id'],
                'first_name' => $user['first_name'],
                'last_name' => $user['last_name'],
                'email' => $user['email'],
                'phone' => $user['phone'],
                'password_hash' => null,
                'status' => 'active',
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            $insertRole = $this->pdo->prepare('INSERT INTO user_roles (id, user_id, role_code, created_at) VALUES (:id, :user_id, :role_code, :created_at)');
            foreach ($roles as $role) {
                $insertRole->execute([
                    'id' => self::uuid(),
                    'user_id' => $id,
                    'role_code' => $role,
                    'created_at' => $now,
                ]);
            }

            $this->pdo->commit();
        } catch (\Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }

            if ($e instanceof PDOException && $this->isUniqueViolation($e, ['users.email', 'idx_users_email', 'key (email)'])) {
                throw new ApiException(409, new ApiError('CONFLICT_USER_EMAIL_EXISTS', 'A user with this email already exists.'));
            }

            throw $e;
        }

        return [
            'user_id' => $id,
            'first_name' => $user['first_name'],
            'last_name' => $user['last_name'],
            'email' => $user['email'],
            'phone' => $user['phone'],
            'provider_id' => $user['provider_id'],
            'roles' => $roles,
            'status' => 'active',
            'password_set' => false,
        ];
    }

    public function getById(string $userId): ?array
    {
        $stmt = $this->pdo->prepare('SELECT id, provider_id, first_name, last_name, email, phone, status, created_at, updated_at FROM users WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $userId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row === false) {
            return null;
        }

        $roleStmt = $this->pdo->prepare('SELECT role_code FROM user_roles WHERE user_id = :user_id ORDER BY role_code ASC');
        $roleStmt->execute(['user_id' => $userId]);
        $roles = array_map(static fn (array $role): string => (string) $role['role_code'], $roleStmt->fetchAll(PDO::FETCH_ASSOC));

        return [
            'user_id' => (string) $row['id'],
            'provider_id' => (string) $row['provider_id'],
            'first_name' => (string) $row['first_name'],
            'last_name' => (string) $row['last_name'],
            'email' => (string) $row['email'],
            'phone' => (string) $row['phone'],
            'status' => (string) $row['status'],
            'roles' => $roles,
            'created_at' => (string) $row['created_at'],
            'updated_at' => (string) $row['updated_at'],
        ];
    }

    public function listByProviderId(?string $providerId): array
    {
        if ($providerId !== null && trim($providerId) !== '') {
            $stmt = $this->pdo->prepare('SELECT id, provider_id, first_name, last_name, email, phone, status, created_at, updated_at FROM users WHERE provider_id = :provider_id ORDER BY created_at ASC');
            $stmt->execute(['provider_id' => $providerId]);
        } else {
            $stmt = $this->pdo->query('SELECT id, provider_id, first_name, last_name, email, phone, status, created_at, updated_at FROM users ORDER BY created_at ASC');
        }

        $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
        if ($users === []) {
            return [];
        }

        $userIds = array_map(static fn (array $row): string => (string) $row['id'], $users);
        $placeholders = implode(',', array_fill(0, count($userIds), '?'));
        $roleStmt = $this->pdo->prepare(sprintf('SELECT user_id, role_code FROM user_roles WHERE user_id IN (%s) ORDER BY role_code ASC', $placeholders));
        $roleStmt->execute($userIds);
        $roleRows = $roleStmt->fetchAll(PDO::FETCH_ASSOC);

        $rolesByUserId = [];
        foreach ($roleRows as $roleRow) {
            $rolesByUserId[(string) $roleRow['user_id']][] = (string) $roleRow['role_code'];
        }

        return array_map(static function (array $row) use ($rolesByUserId): array {
            $userId = (string) $row['id'];

            return [
                'user_id' => $userId,
                'provider_id' => (string) $row['provider_id'],
                'first_name' => (string) $row['first_name'],
                'last_name' => (string) $row['last_name'],
                'email' => (string) $row['email'],
                'phone' => (string) $row['phone'],
                'status' => (string) $row['status'],
                'roles' => $rolesByUserId[$userId] ?? [],
                'created_at' => (string) $row['created_at'],
                'updated_at' => (string) $row['updated_at'],
            ];
        }, $users);
    }

    /** @param list<string> $needles */
    private function isUniqueViolation(PDOException $e, array $needles): bool
    {
        $sqlState = (string) ($e->errorInfo[0] ?? '');
        if ($sqlState !== '23000' && $sqlState !== '23505') {
            return false;
        }

        $haystacks = [strtolower($e->getMessage())];
        if (isset($e->errorInfo[2]) && is_string($e->errorInfo[2])) {
            $haystacks[] = strtolower($e->errorInfo[2]);
        }

        foreach ($needles as $needle) {
            $normalizedNeedle = strtolower($needle);
            foreach ($haystacks as $haystack) {
                if (str_contains($haystack, $normalizedNeedle)) {
                    return true;
                }
            }
        }

        return false;
    }

    private static function uuid(): string
    {
        $data = random_bytes(16);
        $data[6] = chr((ord($data[6]) & 0x0f) | 0x40);
        $data[8] = chr((ord($data[8]) & 0x3f) | 0x80);
        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
    }
}

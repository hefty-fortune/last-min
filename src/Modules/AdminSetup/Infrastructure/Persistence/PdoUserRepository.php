<?php

declare(strict_types=1);

namespace App\Modules\AdminSetup\Infrastructure\Persistence;

use App\Modules\AdminSetup\Application\Port\UserRepository;
use PDO;

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

    private static function uuid(): string
    {
        $data = random_bytes(16);
        $data[6] = chr((ord($data[6]) & 0x0f) | 0x40);
        $data[8] = chr((ord($data[8]) & 0x3f) | 0x80);
        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
    }
}

<?php

declare(strict_types=1);

namespace App\Modules\IdentityAccess\Application\Service;

use App\Common\Api\ApiError;
use App\Common\Api\ApiException;
use App\Modules\IdentityAccess\Application\Port\AuthSessionRepository;
use App\Modules\IdentityAccess\Application\Port\UserAuthRepository;

final class LoginService
{
    private const SESSION_LIFETIME_HOURS = 24;

    public function __construct(
        private UserAuthRepository $users,
        private AuthSessionRepository $sessions,
    ) {
    }

    public function login(string $email, string $password): array
    {
        if (trim($email) === '' || trim($password) === '') {
            throw new ApiException(422, new ApiError('VALIDATION_REQUIRED_FIELD_MISSING', 'email and password are required.'));
        }

        $user = $this->users->findByEmailWithRoles($email);
        if ($user === null) {
            throw new ApiException(401, new ApiError('AUTH_INVALID_CREDENTIALS', 'Invalid email or password.'));
        }

        $passwordHash = $user['password_hash'] ?? null;
        if (!is_string($passwordHash) || $passwordHash === '') {
            throw new ApiException(401, new ApiError('AUTH_INVALID_CREDENTIALS', 'Invalid email or password.'));
        }

        if (!password_verify($password, $passwordHash)) {
            throw new ApiException(401, new ApiError('AUTH_INVALID_CREDENTIALS', 'Invalid email or password.'));
        }

        if (($user['status'] ?? '') !== 'active') {
            throw new ApiException(403, new ApiError('AUTH_ACCOUNT_INACTIVE', 'Account is not active.'));
        }

        $token = 'sess_' . bin2hex(random_bytes(32));
        $expiresAt = (new \DateTimeImmutable())->modify('+' . self::SESSION_LIFETIME_HOURS . ' hours');

        $this->sessions->createSession((string) $user['id'], $token, $expiresAt);

        return [
            'token' => $token,
            'expires_at' => $expiresAt->format(DATE_ATOM),
            'user' => [
                'user_id' => (string) $user['id'],
                'email' => (string) $user['email'],
                'first_name' => (string) $user['first_name'],
                'last_name' => (string) $user['last_name'],
                'roles' => $user['roles'] ?? [],
            ],
        ];
    }

    public function logout(string $token): void
    {
        $this->sessions->revokeByTokenHash(hash('sha256', $token));
    }
}

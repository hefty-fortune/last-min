<?php

declare(strict_types=1);

namespace App\Modules\IdentityAccess\Application\Service;

use App\Common\Api\ApiError;
use App\Common\Api\ApiException;
use App\Modules\IdentityAccess\Application\Port\AuthSessionRepository;
use App\Modules\IdentityAccess\Application\Port\LoginAttemptRepository;
use App\Modules\IdentityAccess\Application\Port\UserAuthRepository;

final class LoginService
{
    private const SESSION_LIFETIME_HOURS = 24;
    private const MAX_FAILURES = 5;
    private const FAILURE_WINDOW_SECONDS = 900;

    public function __construct(
        private UserAuthRepository $users,
        private AuthSessionRepository $sessions,
        private ?LoginAttemptRepository $attempts = null,
    ) {
    }

    public function login(string $email, string $password): array
    {
        if (trim($email) === '' || trim($password) === '') {
            throw new ApiException(422, new ApiError('VALIDATION_REQUIRED_FIELD_MISSING', 'email and password are required.'));
        }

        if ($this->attempts !== null && $this->attempts->countRecentFailures($email, self::FAILURE_WINDOW_SECONDS) >= self::MAX_FAILURES) {
            throw new ApiException(429, new ApiError('AUTH_RATE_LIMITED', 'Too many failed login attempts. Try again later.', retryable: true));
        }

        $user = $this->users->findByEmailWithRoles($email);
        if ($user === null) {
            $this->attempts?->recordFailure($email);
            throw new ApiException(401, new ApiError('AUTH_INVALID_CREDENTIALS', 'Invalid email or password.'));
        }

        $passwordHash = $user['password_hash'] ?? null;
        if (!is_string($passwordHash) || $passwordHash === '') {
            $this->attempts?->recordFailure($email);
            throw new ApiException(401, new ApiError('AUTH_INVALID_CREDENTIALS', 'Invalid email or password.'));
        }

        if (!password_verify($password, $passwordHash)) {
            $this->attempts?->recordFailure($email);
            throw new ApiException(401, new ApiError('AUTH_INVALID_CREDENTIALS', 'Invalid email or password.'));
        }

        if (($user['status'] ?? '') !== 'active') {
            throw new ApiException(403, new ApiError('AUTH_ACCOUNT_INACTIVE', 'Account is not active.'));
        }

        $this->attempts?->clearFailures($email);

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

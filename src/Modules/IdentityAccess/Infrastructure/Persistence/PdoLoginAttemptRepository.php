<?php

declare(strict_types=1);

namespace App\Modules\IdentityAccess\Infrastructure\Persistence;

use App\Modules\IdentityAccess\Application\Port\LoginAttemptRepository;
use PDO;

final class PdoLoginAttemptRepository implements LoginAttemptRepository
{
    public function __construct(private PDO $pdo)
    {
    }

    public function countRecentFailures(string $email, int $windowSeconds): int
    {
        $since = (new \DateTimeImmutable("-{$windowSeconds} seconds"))->format(DATE_ATOM);
        $stmt = $this->pdo->prepare('SELECT COUNT(*) FROM login_attempts WHERE email = :email AND attempted_at >= :since');
        $stmt->execute(['email' => mb_strtolower($email), 'since' => $since]);

        return (int) $stmt->fetchColumn();
    }

    public function recordFailure(string $email): void
    {
        $stmt = $this->pdo->prepare('INSERT INTO login_attempts (id, email, attempted_at) VALUES (:id, :email, :attempted_at)');
        $stmt->execute([
            'id' => bin2hex(random_bytes(16)),
            'email' => mb_strtolower($email),
            'attempted_at' => (new \DateTimeImmutable())->format(DATE_ATOM),
        ]);
    }

    public function clearFailures(string $email): void
    {
        $stmt = $this->pdo->prepare('DELETE FROM login_attempts WHERE email = :email');
        $stmt->execute(['email' => mb_strtolower($email)]);
    }
}

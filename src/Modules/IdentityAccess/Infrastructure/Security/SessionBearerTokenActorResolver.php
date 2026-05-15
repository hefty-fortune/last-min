<?php

declare(strict_types=1);

namespace App\Modules\IdentityAccess\Infrastructure\Security;

use App\Common\Security\ActorContext;
use App\Common\Security\BearerTokenActorResolver;
use App\Modules\IdentityAccess\Application\Port\AuthSessionRepository;
use PDO;

final class SessionBearerTokenActorResolver implements BearerTokenActorResolver
{
    public function __construct(
        private AuthSessionRepository $sessions,
        private PDO $pdo,
    ) {
    }

    public function resolve(string $token): ?ActorContext
    {
        if (trim($token) === '' || !str_starts_with($token, 'sess_')) {
            return null;
        }

        $session = $this->sessions->findActiveByTokenHash(hash('sha256', $token));
        if ($session === null) {
            return null;
        }

        $userId = (string) $session['user_id'];
        $roles = $this->getUserRoles($userId);

        return new ActorContext(
            actorId: $userId,
            upstreamSubject: 'session:' . (string) $session['id'],
            roles: $roles,
            userProfileId: $userId,
        );
    }

    private function getUserRoles(string $userId): array
    {
        $stmt = $this->pdo->prepare('SELECT role_code FROM user_roles WHERE user_id = :user_id ORDER BY role_code ASC');
        $stmt->execute(['user_id' => $userId]);
        $roles = array_map(static fn(array $r): string => (string) $r['role_code'], $stmt->fetchAll(PDO::FETCH_ASSOC));

        return $roles === [] ? ['user'] : $roles;
    }
}

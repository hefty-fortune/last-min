<?php

declare(strict_types=1);

namespace App\Modules\IdentityAccess\Infrastructure\Persistence;

use App\Modules\IdentityAccess\Application\Port\ActorRoleAssignmentRepository;
use PDO;

final class PdoActorRoleAssignmentRepository implements ActorRoleAssignmentRepository
{
    public function __construct(private PDO $pdo)
    {
    }

    public function findActiveRolesByProfileId(string $profileId): array
    {
        $stmt = $this->pdo->prepare('SELECT role_code FROM actor_role_assignments WHERE user_profile_id = :profile_id AND revoked_at IS NULL');
        $stmt->execute(['profile_id' => $profileId]);
        return array_values(array_map(static fn (array $r): string => $r['role_code'], $stmt->fetchAll(PDO::FETCH_ASSOC)));
    }
}

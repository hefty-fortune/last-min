<?php

declare(strict_types=1);

namespace App\Platform\Audit;

use App\Common\Security\ActorContext;
use PDO;

final class PdoAuditLogger implements AuditLogger
{
    public function __construct(private PDO $pdo)
    {
    }

    public function record(?ActorContext $actor, string $action, string $resourceType, ?string $resourceId, array $context = []): void
    {
        $stmt = $this->pdo->prepare('INSERT INTO audit_log (id, actor_id, actor_roles, action, resource_type, resource_id, context, recorded_at) VALUES (:id, :actor_id, :actor_roles, :action, :resource_type, :resource_id, :context, :recorded_at)');
        $stmt->execute([
            'id' => self::uuid(),
            'actor_id' => $actor?->actorId,
            'actor_roles' => $actor === null ? null : json_encode($actor->roles, JSON_THROW_ON_ERROR),
            'action' => $action,
            'resource_type' => $resourceType,
            'resource_id' => $resourceId,
            'context' => $context === [] ? null : json_encode($context, JSON_THROW_ON_ERROR),
            'recorded_at' => (new \DateTimeImmutable())->format(DATE_ATOM),
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

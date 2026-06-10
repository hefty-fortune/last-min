<?php

declare(strict_types=1);

namespace App\Platform\Audit;

use App\Common\Security\ActorContext;

interface AuditLogger
{
    /** @param array<string, mixed> $context */
    public function record(?ActorContext $actor, string $action, string $resourceType, ?string $resourceId, array $context = []): void;
}

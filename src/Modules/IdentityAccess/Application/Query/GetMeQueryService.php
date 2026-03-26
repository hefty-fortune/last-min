<?php

declare(strict_types=1);

namespace App\Modules\IdentityAccess\Application\Query;

use App\Common\Security\ActorContext;

final class GetMeQueryService
{
    public function get(ActorContext $actor): array
    {
        return [
            'actor_id' => $actor->actorId,
            'upstream_subject' => $actor->upstreamSubject,
            'roles' => $actor->roles,
            'default_role' => $actor->roles[0] ?? null,
            'profile_id' => $actor->userProfileId,
        ];
    }
}

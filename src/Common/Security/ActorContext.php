<?php

declare(strict_types=1);

namespace App\Common\Security;

final readonly class ActorContext
{
    /** @param list<string> $roles */
    public function __construct(
        public string $actorId,
        public string $upstreamSubject,
        public array $roles,
        public ?string $userProfileId,
    ) {
    }

    public function hasRole(string $role): bool
    {
        return in_array($role, $this->roles, true);
    }
}

<?php

declare(strict_types=1);

namespace App\Common\Security;

interface BearerTokenActorResolver
{
    public function resolve(string $token): ?ActorContext;
}

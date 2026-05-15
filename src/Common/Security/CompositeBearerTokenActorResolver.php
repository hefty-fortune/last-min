<?php

declare(strict_types=1);

namespace App\Common\Security;

final class CompositeBearerTokenActorResolver implements BearerTokenActorResolver
{
    /** @param list<BearerTokenActorResolver> $resolvers */
    public function __construct(private array $resolvers)
    {
    }

    public function resolve(string $token): ?ActorContext
    {
        foreach ($this->resolvers as $resolver) {
            $actor = $resolver->resolve($token);
            if ($actor !== null) {
                return $actor;
            }
        }

        return null;
    }
}

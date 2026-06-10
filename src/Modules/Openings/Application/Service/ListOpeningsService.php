<?php

declare(strict_types=1);

namespace App\Modules\Openings\Application\Service;

use App\Common\Security\ActorContext;
use App\Modules\Openings\Application\Port\OpeningRepository;

final class ListOpeningsService
{
    public function __construct(
        private OpeningRepository $openings,
        private OpeningAccessService $access,
    ) {
    }

    /** @return list<array<string, mixed>> */
    public function listForProvider(ActorContext $actor, string $providerId, ?string $status, int $limit): array
    {
        $this->access->assertCanReadProvider($actor, $providerId);

        return $this->openings->listByProviderId($providerId, $status, $limit);
    }

    /** @param array<string, mixed> $filters
     *  @return list<array<string, mixed>>
     */
    public function listPublic(array $filters, int $limit): array
    {
        return $this->openings->listPublished($filters, $limit);
    }
}

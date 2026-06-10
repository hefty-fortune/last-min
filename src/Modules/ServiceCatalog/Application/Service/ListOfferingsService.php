<?php

declare(strict_types=1);

namespace App\Modules\ServiceCatalog\Application\Service;

use App\Common\Security\ActorContext;
use App\Modules\ServiceCatalog\Application\Port\OfferingRepository;

final class ListOfferingsService
{
    public function __construct(
        private OfferingRepository $offerings,
        private OfferingAccessService $access,
    ) {
    }

    /** @return list<array<string, mixed>> */
    public function listForProvider(ActorContext $actor, string $providerId, ?string $status, int $limit): array
    {
        $this->access->assertCanManageProvider($actor, $providerId);

        return $this->offerings->listByProviderId($providerId, $status, $limit);
    }

    /** @return list<array<string, mixed>> */
    public function listPublic(string $providerId, int $limit): array
    {
        return $this->offerings->listByProviderId($providerId, 'active', $limit);
    }
}

<?php

declare(strict_types=1);

namespace App\Modules\ServiceCatalog\Application\Service;

use App\Common\Api\ApiError;
use App\Common\Api\ApiException;
use App\Common\Security\ActorContext;
use App\Modules\Openings\Application\Port\OpeningRepository;
use App\Modules\ServiceCatalog\Application\Port\OfferingRepository;
use App\Platform\Audit\AuditLogger;

final class DeleteOfferingService
{
    public function __construct(
        private OfferingRepository $offerings,
        private OfferingAccessService $access,
        private OpeningRepository $openings,
        private AuditLogger $audit,
    ) {
    }

    /** @return array{offering_id: string, deleted: bool} */
    public function delete(ActorContext $actor, string $providerId, string $offeringId): array
    {
        $this->access->assertCanManageProvider($actor, $providerId);

        if ($this->offerings->findByProviderIdAndId($providerId, $offeringId) === null) {
            throw new ApiException(404, new ApiError('OFFERING_NOT_FOUND', 'Offering was not found.'));
        }
        if ($this->openings->countByServiceOfferingId($offeringId) > 0) {
            throw new ApiException(409, new ApiError('CONFLICT_OFFERING_IN_USE', 'Offering still has openings; delete those first.'));
        }

        $this->offerings->delete($offeringId);
        $this->audit->record($actor, 'offering.delete', 'offering', $offeringId, ['provider_id' => $providerId]);

        return ['offering_id' => $offeringId, 'deleted' => true];
    }
}

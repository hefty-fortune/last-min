<?php

declare(strict_types=1);

namespace App\Modules\Openings\Application\Service;

use App\Common\Api\ApiError;
use App\Common\Api\ApiException;
use App\Common\Security\ActorContext;
use App\Modules\Booking\Application\Port\BookingRepository;
use App\Modules\Openings\Application\Port\OpeningRepository;
use App\Platform\Audit\AuditLogger;

final class DeleteOpeningService
{
    /** Live or already-transacted slots must not be hard-deleted. */
    private const DELETABLE_STATES = ['draft', 'cancelled_by_provider', 'expired'];

    public function __construct(
        private OpeningRepository $openings,
        private OpeningAccessService $access,
        private BookingRepository $bookings,
        private AuditLogger $audit,
    ) {
    }

    /** @return array{opening_id: string, deleted: bool} */
    public function delete(ActorContext $actor, string $providerId, string $openingId): array
    {
        $this->access->assertCanManageProvider($actor, $providerId);

        $opening = $this->openings->findByProviderIdAndId($providerId, $openingId);
        if ($opening === null) {
            throw new ApiException(404, new ApiError('OPENING_NOT_FOUND', 'Opening was not found.'));
        }
        if (!in_array((string) $opening['status'], self::DELETABLE_STATES, true)) {
            throw new ApiException(409, new ApiError('CONFLICT_OPENING_STATE_INVALID', 'Only draft, cancelled, or expired openings can be deleted; cancel it first.'));
        }
        if ($this->bookings->countByOpeningId($openingId) > 0) {
            throw new ApiException(409, new ApiError('CONFLICT_OPENING_HAS_BOOKINGS', 'Opening has booking history and cannot be deleted.'));
        }

        $this->openings->delete($openingId);
        $this->audit->record($actor, 'opening.delete', 'opening', $openingId, ['provider_id' => $providerId]);

        return ['opening_id' => $openingId, 'deleted' => true];
    }
}

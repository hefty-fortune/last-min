<?php

declare(strict_types=1);

namespace App\Modules\Openings\Application\Service;

use App\Common\Api\ApiError;
use App\Common\Api\ApiException;
use App\Common\Security\ActorContext;
use App\Modules\Openings\Application\Port\OpeningRepository;
use App\Platform\Persistence\TransactionManager;

final class CancelOpeningService
{
    public function __construct(
        private TransactionManager $tx,
        private OpeningRepository $openings,
        private OpeningAccessService $access,
    ) {
    }

    public function cancel(ActorContext $actor, string $providerId, string $openingId): array
    {
        $this->access->assertCanManageProvider($actor, $providerId);

        return $this->tx->withinTransaction(function () use ($providerId, $openingId): array {
            $opening = $this->openings->lockById($openingId);
            if ($opening === null || (string) $opening['provider_id'] !== $providerId) {
                throw new ApiException(404, new ApiError('OPENING_NOT_FOUND', 'Opening not found.'));
            }
            if (!in_array((string) $opening['status'], ['draft', 'published'], true)) {
                throw new ApiException(409, new ApiError('CONFLICT_OPENING_STATE_INVALID', 'Only draft or published openings can be cancelled in this milestone.'));
            }

            return $this->openings->cancel($openingId);
        });
    }
}

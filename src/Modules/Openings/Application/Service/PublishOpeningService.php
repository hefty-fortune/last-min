<?php

declare(strict_types=1);

namespace App\Modules\Openings\Application\Service;

use App\Common\Api\ApiError;
use App\Common\Api\ApiException;
use App\Common\Security\ActorContext;
use App\Modules\Openings\Application\Port\OpeningRepository;
use App\Platform\Persistence\TransactionManager;

final class PublishOpeningService
{
    public function __construct(
        private TransactionManager $tx,
        private OpeningRepository $openings,
        private OpeningAccessService $access,
    ) {
    }

    public function publish(ActorContext $actor, string $providerId, string $openingId): array
    {
        $this->access->assertCanManageProvider($actor, $providerId);

        return $this->tx->withinTransaction(function () use ($providerId, $openingId): array {
            $opening = $this->openings->lockById($openingId);
            if ($opening === null || (string) $opening['provider_id'] !== $providerId) {
                throw new ApiException(404, new ApiError('OPENING_NOT_FOUND', 'Opening not found.'));
            }
            if ((string) $opening['status'] !== 'draft') {
                throw new ApiException(409, new ApiError('CONFLICT_OPENING_STATE_INVALID', 'Only draft openings can be published.'));
            }

            return $this->openings->publish($openingId);
        });
    }
}

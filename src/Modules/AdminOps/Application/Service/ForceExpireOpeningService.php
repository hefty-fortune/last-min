<?php

declare(strict_types=1);

namespace App\Modules\AdminOps\Application\Service;

use App\Common\Api\ApiError;
use App\Common\Api\ApiException;
use App\Common\Security\ActorContext;
use App\Modules\Openings\Application\Port\OpeningRepository;
use App\Platform\Persistence\TransactionManager;

final class ForceExpireOpeningService
{
    private const EXPIRABLE_STATES = ['draft', 'published', 'reserved'];

    public function __construct(
        private TransactionManager $tx,
        private OpeningRepository $openings,
    ) {
    }

    /** @return array<string, mixed> */
    public function forceExpire(ActorContext $actor, string $openingId): array
    {
        if (!$actor->hasRole('super-admin')) {
            throw new ApiException(403, new ApiError('FORBIDDEN_ROLE_MISSING', 'Super-admin role is required.'));
        }

        return $this->tx->withinTransaction(function () use ($openingId): array {
            $opening = $this->openings->lockById($openingId);
            if ($opening === null) {
                throw new ApiException(404, new ApiError('OPENING_NOT_FOUND', 'Opening was not found.'));
            }
            if (!in_array((string) $opening['status'], self::EXPIRABLE_STATES, true)) {
                throw new ApiException(409, new ApiError('CONFLICT_OPENING_STATE_INVALID', 'Opening cannot be force-expired from its current state.'));
            }

            $this->openings->updateStatus($openingId, 'expired');

            return ['opening_id' => $openingId, 'status' => 'expired'];
        });
    }
}

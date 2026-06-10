<?php

declare(strict_types=1);

namespace App\Modules\Openings\Application\Service;

use App\Common\Api\ApiError;
use App\Common\Api\ApiException;
use App\Common\Security\ActorContext;
use App\Modules\Openings\Application\Port\OpeningRepository;

final class GetOpeningService
{
    public function __construct(
        private OpeningRepository $openings,
        private OpeningAccessService $access,
    ) {
    }

    public function getById(ActorContext $actor, string $providerId, string $openingId): array
    {
        $this->access->assertCanReadProvider($actor, $providerId);

        $opening = $this->openings->findByProviderIdAndId($providerId, $openingId);
        if ($opening === null) {
            throw new ApiException(404, new ApiError('OPENING_NOT_FOUND', 'Opening not found.'));
        }

        return $opening;
    }
}

<?php

declare(strict_types=1);

namespace App\Modules\Openings\Application\Service;

use App\Common\Api\ApiError;
use App\Common\Api\ApiException;
use App\Common\Security\ActorContext;
use App\Modules\Openings\Application\Dto\CreateOpeningRequest;
use App\Modules\Openings\Application\Port\OpeningRepository;

final class CreateOpeningService
{
    public function __construct(private OpeningRepository $openings)
    {
    }

    public function create(ActorContext $actor, CreateOpeningRequest $request): array
    {
        if (!$actor->hasRole('provider')) {
            throw new ApiException(403, new ApiError('FORBIDDEN_ROLE_MISSING', 'Provider role is required.'));
        }

        if (strtotime($request->endsAt) <= strtotime($request->startsAt)) {
            throw new ApiException(422, new ApiError('VALIDATION_TIME_RANGE_INVALID', 'ends_at must be after starts_at.'));
        }

        return $this->openings->createDraft([
            'provider_id' => $request->providerId,
            'service_offering_id' => $request->serviceOfferingId,
            'starts_at' => $request->startsAt,
            'ends_at' => $request->endsAt,
            'price_amount' => (int) ($request->priceOverride['amount_minor'] ?? 0),
            'price_currency' => (string) ($request->priceOverride['currency'] ?? 'EUR'),
        ]);
    }
}

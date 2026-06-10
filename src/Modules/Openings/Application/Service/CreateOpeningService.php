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
    public function __construct(
        private OpeningRepository $openings,
        private OpeningAccessService $access,
    ) {
    }

    public function create(ActorContext $actor, CreateOpeningRequest $request): array
    {
        $this->access->assertCanManageProvider($actor, $request->providerId);
        $startsAt = $this->parseTime($request->startsAt, 'starts_at');
        $endsAt = $this->parseTime($request->endsAt, 'ends_at');

        if ($endsAt <= $startsAt) {
            throw new ApiException(422, new ApiError('VALIDATION_TIME_RANGE_INVALID', 'ends_at must be after starts_at.'));
        }
        if (!$this->openings->serviceOfferingBelongsToProvider($request->serviceOfferingId, $request->providerId)) {
            throw new ApiException(422, new ApiError('VALIDATION_SERVICE_OFFERING_NOT_FOUND', 'service_offering_id must belong to the selected provider.'));
        }
        $priceAmount = (int) ($request->priceOverride['amount_minor'] ?? 0);
        $priceCurrency = strtoupper(trim((string) ($request->priceOverride['currency'] ?? '')));
        if ($priceAmount <= 0 || strlen($priceCurrency) !== 3) {
            throw new ApiException(422, new ApiError('VALIDATION_PRICE_INVALID', 'price_override must contain a positive amount_minor and 3-letter currency.'));
        }

        return $this->openings->createDraft([
            'provider_id' => $request->providerId,
            'service_offering_id' => $request->serviceOfferingId,
            'starts_at' => $startsAt->format(DATE_ATOM),
            'ends_at' => $endsAt->format(DATE_ATOM),
            'price_amount' => $priceAmount,
            'price_currency' => $priceCurrency,
        ]);
    }

    private function parseTime(string $value, string $field): \DateTimeImmutable
    {
        try {
            return new \DateTimeImmutable($value);
        } catch (\Exception) {
            throw new ApiException(422, new ApiError('VALIDATION_DATETIME_INVALID', sprintf('%s must be a valid RFC3339 datetime.', $field)));
        }
    }
}

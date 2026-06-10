<?php

declare(strict_types=1);

namespace App\Modules\ServiceCatalog\Application\Service;

use App\Common\Api\ApiError;
use App\Common\Api\ApiException;
use App\Common\Security\ActorContext;
use App\Modules\ServiceCatalog\Application\Port\OfferingRepository;

final class UpdateOfferingService
{
    public function __construct(
        private OfferingRepository $offerings,
        private OfferingAccessService $access,
    ) {
    }

    /** @param array<string, mixed> $payload
     *  @return array<string, mixed>
     */
    public function update(ActorContext $actor, string $providerId, string $offeringId, array $payload): array
    {
        $this->access->assertCanManageProvider($actor, $providerId);

        $offering = $this->offerings->findByProviderIdAndId($providerId, $offeringId);
        if ($offering === null) {
            throw new ApiException(404, new ApiError('OFFERING_NOT_FOUND', 'Offering was not found.'));
        }

        $changes = [];

        if (array_key_exists('name', $payload)) {
            $name = trim((string) $payload['name']);
            if ($name === '') {
                throw new ApiException(422, new ApiError('VALIDATION_REQUIRED_FIELD', 'Field name must not be empty.'));
            }
            $changes['name'] = $name;
        }

        if (array_key_exists('description', $payload)) {
            $changes['description'] = $payload['description'] === null ? null : (string) $payload['description'];
        }

        if (array_key_exists('duration_minutes', $payload)) {
            $duration = (int) $payload['duration_minutes'];
            if ($duration < 5) {
                throw new ApiException(422, new ApiError('VALIDATION_DURATION_INVALID', 'duration_minutes must be at least 5.'));
            }
            $changes['duration_minutes'] = $duration;
        }

        if (array_key_exists('base_price', $payload)) {
            $basePrice = $payload['base_price'];
            if (!is_array($basePrice) || !isset($basePrice['currency'], $basePrice['amount_minor']) || (int) $basePrice['amount_minor'] < 0) {
                throw new ApiException(422, new ApiError('VALIDATION_PRICE_INVALID', 'base_price requires currency and a non-negative amount_minor.'));
            }
            $changes['price_amount'] = (int) $basePrice['amount_minor'];
            $changes['price_currency'] = strtoupper((string) $basePrice['currency']);
        }

        if (array_key_exists('status', $payload)) {
            $status = (string) $payload['status'];
            if (!in_array($status, ['active', 'inactive'], true)) {
                throw new ApiException(422, new ApiError('VALIDATION_STATUS_INVALID', 'status must be active or inactive.'));
            }
            $changes['status'] = $status;
        }

        if ($changes === []) {
            return $offering;
        }

        return $this->offerings->update($offeringId, $changes);
    }
}

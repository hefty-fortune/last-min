<?php

declare(strict_types=1);

namespace App\Modules\ServiceCatalog\Application\Service;

use App\Common\Api\ApiError;
use App\Common\Api\ApiException;
use App\Common\Security\ActorContext;
use App\Modules\ServiceCatalog\Application\Port\OfferingRepository;

final class CreateOfferingService
{
    public function __construct(
        private OfferingRepository $offerings,
        private OfferingAccessService $access,
    ) {
    }

    /** @param array<string, mixed> $payload
     *  @return array<string, mixed>
     */
    public function create(ActorContext $actor, string $providerId, array $payload): array
    {
        $this->access->assertCanManageProvider($actor, $providerId);

        $name = trim((string) ($payload['name'] ?? ''));
        if ($name === '') {
            throw new ApiException(422, new ApiError('VALIDATION_REQUIRED_FIELD', 'Field name is required.'));
        }

        $duration = (int) ($payload['duration_minutes'] ?? 0);
        if ($duration < 5) {
            throw new ApiException(422, new ApiError('VALIDATION_DURATION_INVALID', 'duration_minutes must be at least 5.'));
        }

        $basePrice = $payload['base_price'] ?? null;
        if (!is_array($basePrice) || !isset($basePrice['currency'], $basePrice['amount_minor']) || (int) $basePrice['amount_minor'] < 0) {
            throw new ApiException(422, new ApiError('VALIDATION_PRICE_INVALID', 'base_price requires currency and a non-negative amount_minor.'));
        }

        return $this->offerings->create([
            'provider_id' => $providerId,
            'name' => $name,
            'description' => isset($payload['description']) ? (string) $payload['description'] : null,
            'duration_minutes' => $duration,
            'price_amount' => (int) $basePrice['amount_minor'],
            'price_currency' => strtoupper((string) $basePrice['currency']),
        ]);
    }
}

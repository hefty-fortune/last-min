<?php

declare(strict_types=1);

namespace App\Modules\Providers\Application\Service;

use App\Common\Api\ApiError;
use App\Common\Api\ApiException;
use App\Common\Security\ActorContext;
use App\Modules\Providers\Application\Port\ProviderRepository;

final class LinkProviderService
{
    public function __construct(private ProviderRepository $providers)
    {
    }

    /**
     * Self-service convenience: links the authenticated provider actor to an
     * individual provider profile, delegating to canonical creation rules.
     *
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    public function link(ActorContext $actor, array $payload): array
    {
        if (!$actor->hasRole('provider')) {
            throw new ApiException(403, new ApiError('FORBIDDEN_ROLE_MISSING', 'Provider role is required.'));
        }
        if ($actor->userProfileId === null) {
            throw new ApiException(401, new ApiError('AUTH_IDENTITY_NOT_LINKED', 'User profile linkage is required.'));
        }

        $providerType = (string) ($payload['provider_type'] ?? 'individual');
        if ($providerType !== 'individual') {
            throw new ApiException(422, new ApiError('VALIDATION_PROVIDER_TYPE_INVALID', 'Self-service linkage supports only individual providers; use canonical organization endpoints.'));
        }

        if ($this->providers->findByOwnerProfileId($actor->userProfileId) !== null) {
            throw new ApiException(409, new ApiError('CONFLICT_PROVIDER_ALREADY_LINKED', 'Actor is already linked to a provider.'));
        }

        $created = $this->providers->createIndividual($actor->userProfileId);

        if (isset($payload['display_name']) && trim((string) $payload['display_name']) !== '') {
            return GetProviderProfileService::mapProvider(
                $this->providers->update((string) $created['provider_id'], ['display_name' => trim((string) $payload['display_name'])])
            );
        }

        $provider = $this->providers->findById((string) $created['provider_id']);
        assert($provider !== null);

        return GetProviderProfileService::mapProvider($provider);
    }
}

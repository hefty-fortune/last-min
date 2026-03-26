<?php

declare(strict_types=1);

namespace App\Modules\Providers\Application\Service;

use App\Common\Api\ApiError;
use App\Common\Api\ApiException;
use App\Common\Security\ActorContext;
use App\Modules\Providers\Application\Dto\CreateProviderRequest;
use App\Modules\Providers\Application\Port\ProviderRepository;

final class CreateProviderService
{
    public function __construct(private ProviderRepository $providers)
    {
    }

    public function create(ActorContext $actor, CreateProviderRequest $request): array
    {
        if (!$actor->hasRole('provider')) {
            throw new ApiException(403, new ApiError('FORBIDDEN_ROLE_MISSING', 'Provider role is required.'));
        }
        if ($actor->userProfileId === null) {
            throw new ApiException(401, new ApiError('AUTH_IDENTITY_NOT_LINKED', 'User profile linkage is required.'));
        }
        if ($request->providerType !== 'individual') {
            throw new ApiException(422, new ApiError('VALIDATION_PROVIDER_TYPE_INVALID', 'This milestone supports only individual provider creation.'));
        }

        return $this->providers->createIndividual($actor->userProfileId);
    }
}

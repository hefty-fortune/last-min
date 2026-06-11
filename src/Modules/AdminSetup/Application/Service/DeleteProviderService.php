<?php

declare(strict_types=1);

namespace App\Modules\AdminSetup\Application\Service;

use App\Common\Api\ApiError;
use App\Common\Api\ApiException;
use App\Common\Security\ActorContext;
use App\Modules\AdminSetup\Application\Port\AdminProviderRepository;
use App\Platform\Audit\AuditLogger;

final class DeleteProviderService
{
    public function __construct(
        private AdminProviderRepository $providers,
        private AuditLogger $audit,
    ) {
    }

    /** @return array{provider_id: string, deleted: bool} */
    public function delete(ActorContext $actor, string $providerId): array
    {
        $this->assertAdmin($actor);

        if ($this->providers->getById($providerId) === null) {
            throw new ApiException(404, new ApiError('PROVIDER_NOT_FOUND', 'Provider not found.'));
        }

        $dependents = $this->providers->countDependents($providerId);
        if ($dependents['users'] > 0 || $dependents['openings'] > 0) {
            throw new ApiException(409, new ApiError(
                'CONFLICT_PROVIDER_IN_USE',
                sprintf('Provider still has %d user(s) and %d opening(s); remove them first.', $dependents['users'], $dependents['openings']),
            ));
        }

        $this->providers->delete($providerId);
        $this->audit->record($actor, 'provider.delete', 'provider', $providerId);

        return ['provider_id' => $providerId, 'deleted' => true];
    }

    private function assertAdmin(ActorContext $actor): void
    {
        if (!$actor->hasRole('admin') && !$actor->hasRole('super-admin')) {
            throw new ApiException(403, new ApiError('FORBIDDEN_ROLE_MISSING', 'Admin role is required.'));
        }
    }
}

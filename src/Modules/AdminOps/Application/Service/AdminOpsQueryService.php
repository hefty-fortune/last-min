<?php

declare(strict_types=1);

namespace App\Modules\AdminOps\Application\Service;

use App\Common\Api\ApiError;
use App\Common\Api\ApiException;
use App\Common\Security\ActorContext;
use App\Modules\AdminOps\Application\Port\AdminOpsReadRepository;

final class AdminOpsQueryService
{
    public function __construct(private AdminOpsReadRepository $readModels)
    {
    }

    /** @param array<string, mixed> $filters
     *  @return list<array<string, mixed>>
     */
    public function listBookings(ActorContext $actor, array $filters, int $limit): array
    {
        $this->assertAdmin($actor);

        return $this->readModels->listBookings($filters, $limit);
    }

    /** @param array<string, mixed> $filters
     *  @return list<array<string, mixed>>
     */
    public function listPayments(ActorContext $actor, array $filters, int $limit): array
    {
        $this->assertAdmin($actor);

        return $this->readModels->listPayments($filters, $limit);
    }

    /** @param array<string, mixed> $filters
     *  @return list<array<string, mixed>>
     */
    public function listRefunds(ActorContext $actor, array $filters, int $limit): array
    {
        $this->assertAdmin($actor);

        return $this->readModels->listRefunds($filters, $limit);
    }

    /** @param array<string, mixed> $filters
     *  @return list<array<string, mixed>>
     */
    public function listStripeWebhookEvents(ActorContext $actor, array $filters, int $limit): array
    {
        $this->assertAdmin($actor);

        return $this->readModels->listStripeWebhookEvents($filters, $limit);
    }

    private function assertAdmin(ActorContext $actor): void
    {
        if (!$actor->hasRole('admin') && !$actor->hasRole('super-admin')) {
            throw new ApiException(403, new ApiError('FORBIDDEN_ROLE_MISSING', 'Admin role is required.'));
        }
    }
}

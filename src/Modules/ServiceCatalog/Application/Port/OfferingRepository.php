<?php

declare(strict_types=1);

namespace App\Modules\ServiceCatalog\Application\Port;

interface OfferingRepository
{
    /** @return array<string, mixed>|null */
    public function findByProviderIdAndId(string $providerId, string $offeringId): ?array;

    /** @return list<array<string, mixed>> */
    public function listByProviderId(string $providerId, ?string $status, int $limit): array;

    /** @param array<string, mixed> $offering
     *  @return array<string, mixed>
     */
    public function create(array $offering): array;

    /** @param array<string, mixed> $changes
     *  @return array<string, mixed>
     */
    public function update(string $offeringId, array $changes): array;
}

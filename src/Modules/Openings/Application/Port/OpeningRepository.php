<?php

declare(strict_types=1);

namespace App\Modules\Openings\Application\Port;

interface OpeningRepository
{
    public function serviceOfferingBelongsToProvider(string $serviceOfferingId, string $providerId): bool;

    public function createDraft(array $data): array;

    /** @return array<string, mixed>|null */
    public function findById(string $openingId): ?array;

    /** @return array<string, mixed>|null */
    public function findByProviderIdAndId(string $providerId, string $openingId): ?array;

    /** @return list<array<string, mixed>> */
    public function listByProviderId(string $providerId, ?string $status, int $limit): array;

    /** @param array<string, mixed> $filters
     *  @return list<array<string, mixed>>
     */
    public function listPublished(array $filters, int $limit): array;

    public function lockById(string $openingId): ?array;

    public function publish(string $openingId): array;

    public function cancel(string $openingId): array;

    public function updateStatus(string $openingId, string $status): void;
}

<?php

declare(strict_types=1);

namespace App\Modules\AdminOps\Application\Port;

interface AdminOpsReadRepository
{
    /** @param array<string, mixed> $filters
     *  @return list<array<string, mixed>>
     */
    public function listBookings(array $filters, int $limit): array;

    /** @param array<string, mixed> $filters
     *  @return list<array<string, mixed>>
     */
    public function listPayments(array $filters, int $limit): array;

    /** @param array<string, mixed> $filters
     *  @return list<array<string, mixed>>
     */
    public function listRefunds(array $filters, int $limit): array;

    /** @param array<string, mixed> $filters
     *  @return list<array<string, mixed>>
     */
    public function listStripeWebhookEvents(array $filters, int $limit): array;
}

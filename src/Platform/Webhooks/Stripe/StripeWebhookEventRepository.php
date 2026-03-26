<?php

declare(strict_types=1);

namespace App\Platform\Webhooks\Stripe;

interface StripeWebhookEventRepository
{
    public function findByStripeEventId(string $eventId): ?array;

    public function insertReceived(string $eventId, string $eventType, array $payload, bool $signatureValid): void;

    public function markProcessed(string $eventId): void;

    public function markFailed(string $eventId, string $reason): void;
}

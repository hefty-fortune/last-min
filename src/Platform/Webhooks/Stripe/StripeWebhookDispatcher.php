<?php

declare(strict_types=1);

namespace App\Platform\Webhooks\Stripe;

final class StripeWebhookDispatcher
{
    public function dispatch(array $event): void
    {
        $type = (string) ($event['type'] ?? '');
        if (in_array($type, ['payment_intent.succeeded', 'payment_intent.payment_failed'], true)) {
            return;
        }
    }
}

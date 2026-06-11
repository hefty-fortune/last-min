<?php

declare(strict_types=1);

namespace App\Platform\Webhooks\Stripe;

use App\Common\Api\ApiException;
use App\Modules\Payments\Application\Service\SettlePaymentOutcomeService;

final class StripeWebhookDispatcher
{
    public function __construct(private ?SettlePaymentOutcomeService $settlement = null)
    {
    }

    public function dispatch(array $event): void
    {
        if ($this->settlement === null) {
            return;
        }

        $type = (string) ($event['type'] ?? '');
        $intentId = (string) ($event['data']['object']['id'] ?? '');
        if ($intentId === '') {
            return;
        }

        // Unknown intents and already-settled payments are ignored: Stripe
        // retries deliveries, so dispatch must stay idempotent.
        try {
            if ($type === 'payment_intent.succeeded') {
                $this->settlement->succeedByStripeIntentId($intentId);
            } elseif ($type === 'payment_intent.payment_failed') {
                $reason = (string) ($event['data']['object']['last_payment_error']['message'] ?? 'payment_failed');
                $this->settlement->failByStripeIntentId($intentId, $reason);
            }
        } catch (ApiException $e) {
            if ($e->error->code !== 'PAYMENT_STATE_INVALID') {
                throw $e;
            }
        }
    }
}

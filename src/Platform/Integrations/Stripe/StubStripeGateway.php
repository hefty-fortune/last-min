<?php

declare(strict_types=1);

namespace App\Platform\Integrations\Stripe;

final class StubStripeGateway implements StripeGateway
{
    public function createPaymentIntent(string $paymentId, int $amountMinor, string $currency, ?string $returnUrl): array
    {
        return [
            'payment_intent_id' => 'pi_' . substr(str_replace('-', '', $paymentId), 0, 24),
            'client_secret' => 'secret_' . substr(sha1($paymentId . $amountMinor . $currency . ($returnUrl ?? '')), 0, 24),
            'status' => 'requires_action',
        ];
    }
}

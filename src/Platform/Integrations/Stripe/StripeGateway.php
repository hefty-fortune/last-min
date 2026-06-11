<?php

declare(strict_types=1);

namespace App\Platform\Integrations\Stripe;

interface StripeGateway
{
    public function createPaymentIntent(string $paymentId, int $amountMinor, string $currency, ?string $returnUrl): array;

    /** @return array{refund_id: string, status: string} */
    public function createRefund(string $refundId, string $paymentIntentId, int $amountMinor): array;
}

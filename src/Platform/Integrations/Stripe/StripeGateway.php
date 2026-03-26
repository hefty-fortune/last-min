<?php

declare(strict_types=1);

namespace App\Platform\Integrations\Stripe;

interface StripeGateway
{
    public function createPaymentIntent(string $paymentId, int $amountMinor, string $currency, ?string $returnUrl): array;
}

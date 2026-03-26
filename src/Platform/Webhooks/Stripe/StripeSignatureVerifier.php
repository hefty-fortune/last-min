<?php

declare(strict_types=1);

namespace App\Platform\Webhooks\Stripe;

final class StripeSignatureVerifier
{
    public function __construct(private string $secret)
    {
    }

    public function isValid(string $rawBody, ?string $signature): bool
    {
        if ($signature === null || $signature === '') {
            return false;
        }

        $expected = hash_hmac('sha256', $rawBody, $this->secret);
        return hash_equals($expected, $signature);
    }
}

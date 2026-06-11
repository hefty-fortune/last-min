<?php

declare(strict_types=1);

namespace App\Platform\Webhooks\Stripe;

final class StripeSignatureVerifier
{
    private const TOLERANCE_SECONDS = 300;

    public function __construct(private string $secret)
    {
    }

    public function isValid(string $rawBody, ?string $signature): bool
    {
        if ($signature === null || $signature === '') {
            return false;
        }

        // Real Stripe scheme: "t=<unix>,v1=<hmac of '<t>.<body>'>", possibly
        // with multiple v1 entries during secret rotation.
        if (str_contains($signature, 't=') && str_contains($signature, 'v1=')) {
            return $this->isValidStripeScheme($rawBody, $signature);
        }

        // Legacy dev scheme: plain HMAC of the raw body (used by local tooling).
        $expected = hash_hmac('sha256', $rawBody, $this->secret);

        return hash_equals($expected, $signature);
    }

    private function isValidStripeScheme(string $rawBody, string $signature): bool
    {
        $timestamp = null;
        $candidates = [];
        foreach (explode(',', $signature) as $part) {
            [$key, $value] = array_pad(explode('=', trim($part), 2), 2, '');
            if ($key === 't') {
                $timestamp = (int) $value;
            } elseif ($key === 'v1') {
                $candidates[] = $value;
            }
        }

        if ($timestamp === null || $candidates === []) {
            return false;
        }

        if (abs(time() - $timestamp) > self::TOLERANCE_SECONDS) {
            return false;
        }

        $expected = hash_hmac('sha256', $timestamp . '.' . $rawBody, $this->secret);
        foreach ($candidates as $candidate) {
            if (hash_equals($expected, $candidate)) {
                return true;
            }
        }

        return false;
    }
}

<?php

declare(strict_types=1);

namespace App\Platform\Integrations\Stripe;

use App\Common\Api\ApiError;
use App\Common\Api\ApiException;

/**
 * Real Stripe gateway (STRIPE_MODE=real): creates PaymentIntents against the
 * live Stripe API using the secret key. Deliberately dependency-free (curl)
 * to keep the core framework-agnostic.
 */
final class HttpStripeGateway implements StripeGateway
{
    private const API_BASE = 'https://api.stripe.com/v1';

    public function __construct(private string $secretKey)
    {
    }

    public function createPaymentIntent(string $paymentId, int $amountMinor, string $currency, ?string $returnUrl): array
    {
        $payload = [
            'amount' => $amountMinor,
            'currency' => strtolower($currency),
            'metadata[payment_id]' => $paymentId,
            'automatic_payment_methods[enabled]' => 'true',
        ];

        $response = $this->post('/payment_intents', $payload, idempotencyKey: 'pi-create-' . $paymentId);

        return [
            'payment_intent_id' => (string) $response['id'],
            'client_secret' => (string) $response['client_secret'],
            'status' => (string) $response['status'],
        ];
    }

    /** @param array<string, string|int> $payload
     *  @return array<string, mixed>
     */
    private function post(string $path, array $payload, string $idempotencyKey): array
    {
        $ch = curl_init(self::API_BASE . $path);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => http_build_query($payload),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 15,
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $this->secretKey,
                'Content-Type: application/x-www-form-urlencoded',
                // Stripe-native idempotency: retries of the same create are safe.
                'Idempotency-Key: ' . $idempotencyKey,
            ],
        ]);

        $raw = curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($raw === false) {
            throw new ApiException(502, new ApiError('PAYMENT_GATEWAY_UNREACHABLE', 'Stripe request failed: ' . $curlError, retryable: true));
        }

        $decoded = json_decode((string) $raw, true);
        if (!is_array($decoded)) {
            throw new ApiException(502, new ApiError('PAYMENT_GATEWAY_ERROR', 'Stripe returned an unreadable response.', retryable: true));
        }

        if ($status >= 400) {
            $message = (string) ($decoded['error']['message'] ?? 'Stripe rejected the request.');
            throw new ApiException(502, new ApiError('PAYMENT_GATEWAY_ERROR', $message, retryable: $status >= 500));
        }

        return $decoded;
    }
}

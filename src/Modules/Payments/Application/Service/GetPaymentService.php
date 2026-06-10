<?php

declare(strict_types=1);

namespace App\Modules\Payments\Application\Service;

use App\Common\Api\ApiError;
use App\Common\Api\ApiException;
use App\Common\Security\ActorContext;
use App\Modules\Payments\Application\Port\PaymentRepository;
use App\Modules\Providers\Application\Port\ProviderRepository;

final class GetPaymentService
{
    public function __construct(
        private PaymentRepository $payments,
        private ProviderRepository $providers,
    ) {
    }

    /** @return array<string, mixed> */
    public function getById(ActorContext $actor, string $paymentId, ?string $bookingId = null): array
    {
        $payment = $this->payments->findById($paymentId);
        if ($payment === null || ($bookingId !== null && $payment['booking_id'] !== $bookingId)) {
            throw new ApiException(404, new ApiError('PAYMENT_NOT_FOUND', 'Payment was not found.'));
        }

        $this->assertCanReadPayment($actor, $payment);

        return [
            'payment_id' => $payment['id'],
            'booking_id' => $payment['booking_id'],
            'state' => $payment['state'],
            'amount' => ['currency' => $payment['currency'], 'amount_minor' => (int) $payment['amount']],
            'stripe_payment_intent_id' => $payment['stripe_payment_intent_id'],
            'captured_at' => $payment['captured_at'],
            'failed_reason' => $payment['failed_reason'],
            'created_at' => $payment['created_at'],
            'updated_at' => $payment['updated_at'],
        ];
    }

    /** @param array<string, mixed> $payment */
    private function assertCanReadPayment(ActorContext $actor, array $payment): void
    {
        if ($actor->hasRole('admin') || $actor->hasRole('super-admin')) {
            return;
        }

        if ($actor->userProfileId === null) {
            throw new ApiException(401, new ApiError('AUTH_IDENTITY_NOT_LINKED', 'User profile linkage is required.'));
        }

        if ($actor->hasRole('client') && $payment['client_user_profile_id'] === $actor->userProfileId) {
            return;
        }

        if ($actor->hasRole('provider')) {
            $provider = $this->providers->findById((string) $payment['provider_id']);
            if ($provider !== null && ($provider['owner_user_profile_id'] ?? null) === $actor->userProfileId) {
                return;
            }
        }

        throw new ApiException(403, new ApiError('FORBIDDEN_PAYMENT_SCOPE', 'Actor cannot access this payment.'));
    }
}

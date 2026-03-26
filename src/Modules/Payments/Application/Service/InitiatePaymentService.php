<?php

declare(strict_types=1);

namespace App\Modules\Payments\Application\Service;

use App\Common\Api\ApiError;
use App\Common\Api\ApiException;
use App\Common\Security\ActorContext;
use App\Modules\Booking\Application\Port\BookingRepository;
use App\Modules\Payments\Application\Port\PaymentRepository;
use App\Platform\Integrations\Stripe\StripeGateway;

final class InitiatePaymentService
{
    public function __construct(
        private BookingRepository $bookings,
        private PaymentRepository $payments,
        private StripeGateway $stripe,
    ) {
    }

    public function initiate(ActorContext $actor, string $bookingId, string $paymentMethodType, ?string $returnUrl): array
    {
        if (!$actor->hasRole('client')) {
            throw new ApiException(403, new ApiError('FORBIDDEN_ROLE_MISSING', 'Client role is required.'));
        }

        $booking = $this->bookings->findById($bookingId);
        if ($booking === null) {
            throw new ApiException(404, new ApiError('BOOKING_NOT_FOUND', 'Booking not found.'));
        }
        if ($booking['client_user_profile_id'] !== $actor->userProfileId) {
            throw new ApiException(403, new ApiError('FORBIDDEN_BOOKING_SCOPE', 'Booking does not belong to actor.'));
        }
        if (!in_array($booking['state'], ['reserved', 'payment_pending'], true)) {
            throw new ApiException(409, new ApiError('PAYMENT_BOOKING_STATE_INVALID', 'Payment cannot be initiated for current booking state.'));
        }
        if ($paymentMethodType !== 'card') {
            throw new ApiException(422, new ApiError('VALIDATION_PAYMENT_METHOD_INVALID', 'Only card is supported in this milestone.'));
        }

        $payment = $this->payments->findByBookingId($bookingId);
        if ($payment === null) {
            $payment = $this->payments->createInitiated([
                'booking_id' => $bookingId,
                'provider_id' => $booking['provider_id'],
                'client_user_profile_id' => $actor->userProfileId,
                'amount' => (int) $booking['payment_required_amount'],
                'currency' => $booking['payment_currency'],
            ]);
        } else {
            $payment = ['payment_id' => $payment['id'], 'state' => $payment['state'], 'amount' => ['currency' => $payment['currency'], 'amount_minor' => (int) $payment['amount']]];
        }

        $gateway = $this->stripe->createPaymentIntent($payment['payment_id'], $payment['amount']['amount_minor'], $payment['amount']['currency'], $returnUrl);
        $this->payments->attachStripeIntent($payment['payment_id'], $gateway['payment_intent_id']);

        return [
            'payment_id' => $payment['payment_id'],
            'state' => $payment['state'],
            'amount' => $payment['amount'],
            'gateway_status' => ['provider' => 'stripe', 'status' => $gateway['status']],
            'stripe' => ['payment_intent_id' => $gateway['payment_intent_id'], 'client_secret' => $gateway['client_secret']],
        ];
    }
}

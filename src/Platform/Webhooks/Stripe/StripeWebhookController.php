<?php

declare(strict_types=1);

namespace App\Platform\Webhooks\Stripe;

use App\Common\Api\ApiError;
use App\Common\Api\ApiException;
use App\Common\Api\ApiResponse;
use App\Common\Http\Request;

final class StripeWebhookController
{
    public function __construct(
        private StripeSignatureVerifier $verifier,
        private StripeWebhookEventRepository $events,
        private StripeWebhookDispatcher $dispatcher,
    ) {
    }

    public function ingest(Request $request): ApiResponse
    {
        if (!$this->verifier->isValid($request->rawBody, $request->header('Stripe-Signature'))) {
            throw new ApiException(400, new ApiError('WEBHOOK_SIGNATURE_INVALID', 'Invalid Stripe signature.'));
        }

        $event = json_decode($request->rawBody, true, 512, JSON_THROW_ON_ERROR);
        $eventId = (string) ($event['id'] ?? '');
        $eventType = (string) ($event['type'] ?? 'unknown');

        if ($eventId === '') {
            throw new ApiException(400, new ApiError('WEBHOOK_PAYLOAD_INVALID', 'Missing Stripe event id.'));
        }

        if ($this->events->findByStripeEventId($eventId) !== null) {
            return ApiResponse::ok(['received' => true, 'meta' => ['deduplicated' => true]]);
        }

        $this->events->insertReceived($eventId, $eventType, $event, true);

        try {
            $this->dispatcher->dispatch($event);
            $this->events->markProcessed($eventId);
        } catch (\Throwable $e) {
            $this->events->markFailed($eventId, $e->getMessage());
            throw new ApiException(500, new ApiError('INTERNAL_WEBHOOK_PROCESSING_FAILED', 'Webhook processing failed.', retryable: true));
        }

        return ApiResponse::ok(['received' => true]);
    }
}

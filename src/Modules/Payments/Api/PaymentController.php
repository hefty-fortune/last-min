<?php

declare(strict_types=1);

namespace App\Modules\Payments\Api;

use App\Common\Api\ApiResponse;
use App\Common\Http\Request;
use App\Common\Security\ActorContext;
use App\Common\Api\ApiError;
use App\Common\Api\ApiException;
use App\Modules\Payments\Application\Service\GetPaymentService;
use App\Modules\Payments\Application\Service\InitiatePaymentService;
use App\Modules\Payments\Application\Service\SettlePaymentOutcomeService;
use App\Platform\Idempotency\IdempotencyExecutor;
use OpenApi\Attributes as OA;

final class PaymentController
{
    public function __construct(
        private InitiatePaymentService $service,
        private GetPaymentService $getService,
        private SettlePaymentOutcomeService $settlement,
        private IdempotencyExecutor $idempotency,
        private bool $simulationEnabled = true,
    ) {
    }

    #[OA\Post(
        path: '/bookings/{booking_id}/payments/initiate',
        summary: 'Initiate a payment for a booking',
        security: [['apiKey' => []]],
        tags: ['Payments'],
        parameters: [
            new OA\Parameter(name: 'booking_id', in: 'path', required: true, schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'Idempotency-Key', in: 'header', required: true, schema: new OA\Schema(type: 'string')),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['payment_method_type'],
                properties: [
                    new OA\Property(property: 'payment_method_type', type: 'string'),
                    new OA\Property(property: 'return_url', type: 'string', nullable: true),
                ],
            ),
        ),
        responses: [
            new OA\Response(response: 201, description: 'Payment initiated', content: new OA\JsonContent(ref: '#/components/schemas/PaymentInitiatedResponse')),
        ],
    )]
    public function initiate(ActorContext $actor, Request $request, string $bookingId): ApiResponse
    {
        $key = $request->header('Idempotency-Key') ?? '';
        $payload = $request->body + ['booking_id' => $bookingId];

        $result = $this->idempotency->execute('payment.initiate', $key, $payload, function () use ($actor, $request, $bookingId): array {
            $data = $this->service->initiate($actor, $bookingId, (string) $request->body['payment_method_type'], $request->body['return_url'] ?? null);
            return [
                'status' => 201,
                'body' => ['data' => $data, 'meta' => ['request_id' => uniqid('req_', true), 'idempotency_replayed' => false]],
                'resource_type' => 'payment',
                'resource_id' => $data['payment_id'],
            ];
        });

        return new ApiResponse($result['status'], $result['body']);
    }

    #[OA\Get(
        path: '/payments/{payment_id}',
        summary: 'Get payment status for permitted actors',
        security: [['apiKey' => []]],
        tags: ['Payments'],
        parameters: [
            new OA\Parameter(name: 'payment_id', in: 'path', required: true, schema: new OA\Schema(type: 'string')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Payment details'),
            new OA\Response(response: 403, description: 'Forbidden', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
            new OA\Response(response: 404, description: 'Not found', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
        ],
    )]
    public function get(ActorContext $actor, string $paymentId): ApiResponse
    {
        $data = $this->getService->getById($actor, $paymentId);

        return ApiResponse::ok(['data' => $data, 'meta' => ['request_id' => uniqid('req_', true)]]);
    }

    #[OA\Get(
        path: '/bookings/{booking_id}/payments/{payment_id}',
        summary: 'Get payment status scoped to a booking',
        security: [['apiKey' => []]],
        tags: ['Payments'],
        parameters: [
            new OA\Parameter(name: 'booking_id', in: 'path', required: true, schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'payment_id', in: 'path', required: true, schema: new OA\Schema(type: 'string')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Payment details'),
            new OA\Response(response: 403, description: 'Forbidden', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
            new OA\Response(response: 404, description: 'Not found', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
        ],
    )]
    public function getForBooking(ActorContext $actor, string $bookingId, string $paymentId): ApiResponse
    {
        $data = $this->getService->getById($actor, $paymentId, $bookingId);

        return ApiResponse::ok(['data' => $data, 'meta' => ['request_id' => uniqid('req_', true)]]);
    }

    #[OA\Post(
        path: '/payments/{payment_id}:simulate-succeed',
        summary: 'DEV ONLY: simulate a successful gateway outcome (same path as the Stripe webhook)',
        security: [['apiKey' => []]],
        tags: ['Payments'],
        parameters: [
            new OA\Parameter(name: 'payment_id', in: 'path', required: true, schema: new OA\Schema(type: 'string')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Payment captured, booking confirmed'),
            new OA\Response(response: 409, description: 'Already settled', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
        ],
    )]
    public function simulateSucceed(ActorContext $actor, string $paymentId): ApiResponse
    {
        $this->assertCanSimulate($actor);
        $data = $this->settlement->succeed($paymentId);

        return ApiResponse::ok(['data' => $data, 'meta' => ['request_id' => uniqid('req_', true)]]);
    }

    #[OA\Post(
        path: '/payments/{payment_id}:simulate-fail',
        summary: 'DEV ONLY: simulate a failed gateway outcome (same path as the Stripe webhook)',
        security: [['apiKey' => []]],
        tags: ['Payments'],
        parameters: [
            new OA\Parameter(name: 'payment_id', in: 'path', required: true, schema: new OA\Schema(type: 'string')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Payment failed, booking released'),
            new OA\Response(response: 409, description: 'Already settled', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
        ],
    )]
    public function simulateFail(ActorContext $actor, Request $request, string $paymentId): ApiResponse
    {
        $this->assertCanSimulate($actor);
        $reason = isset($request->body['reason']) ? (string) $request->body['reason'] : 'simulated_failure';
        $data = $this->settlement->fail($paymentId, $reason);

        return ApiResponse::ok(['data' => $data, 'meta' => ['request_id' => uniqid('req_', true)]]);
    }

    private function assertCanSimulate(ActorContext $actor): void
    {
        if (!$actor->hasRole('admin') && !$actor->hasRole('super-admin')) {
            throw new ApiException(403, new ApiError('FORBIDDEN_ROLE_MISSING', 'Admin role is required.'));
        }

        if (!$this->simulationEnabled) {
            throw new ApiException(403, new ApiError('SIMULATION_DISABLED', 'Payment simulation is disabled when STRIPE_MODE=real; outcomes arrive via Stripe webhooks.'));
        }
    }
}

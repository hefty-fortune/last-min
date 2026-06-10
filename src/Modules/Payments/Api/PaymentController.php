<?php

declare(strict_types=1);

namespace App\Modules\Payments\Api;

use App\Common\Api\ApiResponse;
use App\Common\Http\Request;
use App\Common\Security\ActorContext;
use App\Modules\Payments\Application\Service\GetPaymentService;
use App\Modules\Payments\Application\Service\InitiatePaymentService;
use App\Platform\Idempotency\IdempotencyExecutor;
use OpenApi\Attributes as OA;

final class PaymentController
{
    public function __construct(
        private InitiatePaymentService $service,
        private GetPaymentService $getService,
        private IdempotencyExecutor $idempotency,
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
}

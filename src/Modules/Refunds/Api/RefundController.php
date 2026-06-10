<?php

declare(strict_types=1);

namespace App\Modules\Refunds\Api;

use App\Common\Api\ApiResponse;
use App\Common\Http\Request;
use App\Common\Security\ActorContext;
use App\Modules\Refunds\Application\Service\ApproveRefundService;
use App\Modules\Refunds\Application\Service\ListBookingRefundsService;
use App\Platform\Idempotency\IdempotencyExecutor;
use OpenApi\Attributes as OA;

final class RefundController
{
    public function __construct(
        private ListBookingRefundsService $listService,
        private ApproveRefundService $approveService,
        private IdempotencyExecutor $idempotency,
    ) {
    }

    #[OA\Get(
        path: '/bookings/{booking_id}/refunds',
        summary: 'List refunds for a booking',
        security: [['apiKey' => []]],
        tags: ['Refunds'],
        parameters: [
            new OA\Parameter(name: 'booking_id', in: 'path', required: true, schema: new OA\Schema(type: 'string')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Refund history for the booking'),
            new OA\Response(response: 403, description: 'Forbidden', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
            new OA\Response(response: 404, description: 'Not found', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
        ],
    )]
    public function listForBooking(ActorContext $actor, string $bookingId): ApiResponse
    {
        $data = $this->listService->listForBooking($actor, $bookingId);

        return ApiResponse::ok(['data' => $data, 'meta' => ['request_id' => uniqid('req_', true)]]);
    }

    #[OA\Post(
        path: '/refunds/{refund_id}:approve',
        summary: 'Approve a requested refund (admin exception workflow)',
        security: [['apiKey' => []]],
        tags: ['Refunds'],
        parameters: [
            new OA\Parameter(name: 'refund_id', in: 'path', required: true, schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'Idempotency-Key', in: 'header', required: true, schema: new OA\Schema(type: 'string')),
        ],
        requestBody: new OA\RequestBody(
            required: false,
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'note', type: 'string', nullable: true),
                ],
            ),
        ),
        responses: [
            new OA\Response(response: 200, description: 'Refund approved'),
            new OA\Response(response: 403, description: 'Forbidden', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
            new OA\Response(response: 409, description: 'Invalid refund state', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
        ],
    )]
    public function approve(ActorContext $actor, Request $request, string $refundId): ApiResponse
    {
        $key = $request->header('Idempotency-Key') ?? '';
        $payload = $request->body + ['refund_id' => $refundId];
        $result = $this->idempotency->execute('refund.approve', $key, $payload, function () use ($actor, $request, $refundId): array {
            $note = isset($request->body['note']) ? (string) $request->body['note'] : null;
            $data = $this->approveService->approve($actor, $refundId, $note);

            return [
                'status' => 200,
                'body' => ['data' => $data, 'meta' => ['request_id' => uniqid('req_', true), 'idempotency_replayed' => false]],
                'resource_type' => 'refund',
                'resource_id' => $data['refund_id'],
            ];
        });

        return new ApiResponse($result['status'], $result['body']);
    }
}

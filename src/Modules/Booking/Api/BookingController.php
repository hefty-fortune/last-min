<?php

declare(strict_types=1);

namespace App\Modules\Booking\Api;

use App\Common\Api\ApiResponse;
use App\Common\Http\Request;
use App\Common\Security\ActorContext;
use App\Modules\Booking\Application\Dto\CreateBookingRequest;
use App\Modules\Booking\Application\Service\CreateBookingService;
use App\Modules\Booking\Application\Service\GetBookingService;
use App\Modules\Booking\Application\Service\ListMyBookingsService;
use App\Platform\Idempotency\IdempotencyExecutor;
use OpenApi\Attributes as OA;

final class BookingController
{
    public function __construct(
        private CreateBookingService $service,
        private GetBookingService $getService,
        private ListMyBookingsService $listMineService,
        private IdempotencyExecutor $idempotency,
    ) {
    }

    #[OA\Post(
        path: '/bookings',
        summary: 'Create a booking',
        security: [['apiKey' => []]],
        tags: ['Bookings'],
        parameters: [
            new OA\Parameter(name: 'Idempotency-Key', in: 'header', required: true, schema: new OA\Schema(type: 'string')),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['opening_id'],
                properties: [
                    new OA\Property(property: 'opening_id', type: 'string'),
                    new OA\Property(property: 'client_note', type: 'string', nullable: true),
                ],
            ),
        ),
        responses: [
            new OA\Response(response: 201, description: 'Booking created', content: new OA\JsonContent(ref: '#/components/schemas/BookingCreatedResponse')),
        ],
    )]
    public function create(ActorContext $actor, Request $request): ApiResponse
    {
        $key = $request->header('Idempotency-Key') ?? '';
        $result = $this->idempotency->execute('booking.create', $key, $request->body, function () use ($actor, $request): array {
            $data = $this->service->create($actor, new CreateBookingRequest((string) $request->body['opening_id'], $request->body['client_note'] ?? null));

            return [
                'status' => 201,
                'body' => ['data' => $data, 'meta' => ['request_id' => uniqid('req_', true), 'idempotency_replayed' => false]],
                'resource_type' => 'booking',
                'resource_id' => $data['booking_id'],
            ];
        });

        return new ApiResponse($result['status'], $result['body']);
    }

    #[OA\Get(
        path: '/bookings/{booking_id}',
        summary: 'Get booking details for permitted actors',
        security: [['apiKey' => []]],
        tags: ['Bookings'],
        parameters: [
            new OA\Parameter(name: 'booking_id', in: 'path', required: true, schema: new OA\Schema(type: 'string')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Booking details'),
            new OA\Response(response: 403, description: 'Forbidden', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
            new OA\Response(response: 404, description: 'Not found', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
        ],
    )]
    public function get(ActorContext $actor, string $bookingId): ApiResponse
    {
        $data = $this->getService->getById($actor, $bookingId);

        return ApiResponse::ok(['data' => $data, 'meta' => ['request_id' => uniqid('req_', true)]]);
    }

    #[OA\Get(
        path: '/me/bookings',
        summary: 'List bookings for the authenticated client',
        security: [['apiKey' => []]],
        tags: ['Bookings'],
        parameters: [
            new OA\Parameter(name: 'state', in: 'query', required: false, schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'limit', in: 'query', required: false, schema: new OA\Schema(type: 'integer', default: 20)),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Bookings for the authenticated client'),
            new OA\Response(response: 403, description: 'Forbidden', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
        ],
    )]
    public function listMine(ActorContext $actor, Request $request): ApiResponse
    {
        $state = isset($request->attributes['query']['state']) ? (string) $request->attributes['query']['state'] : null;
        $limit = isset($request->attributes['query']['limit']) ? (int) $request->attributes['query']['limit'] : 20;
        $data = $this->listMineService->listForActor($actor, $state, $limit);

        return ApiResponse::ok(['data' => $data, 'meta' => ['request_id' => uniqid('req_', true)]]);
    }
}

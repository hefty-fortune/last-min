<?php

declare(strict_types=1);

namespace App\Modules\Booking\Api;

use App\Common\Api\ApiResponse;
use App\Common\Http\Request;
use App\Common\Security\ActorContext;
use App\Modules\Booking\Application\Dto\CreateBookingRequest;
use App\Modules\Booking\Application\Service\CreateBookingService;
use App\Platform\Idempotency\IdempotencyExecutor;
use OpenApi\Attributes as OA;

final class BookingController
{
    public function __construct(private CreateBookingService $service, private IdempotencyExecutor $idempotency)
    {
    }

    #[OA\Post(
        path: '/bookings',
        summary: 'Create a booking',
        security: [['bearerAuth' => []]],
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
}

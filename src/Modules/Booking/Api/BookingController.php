<?php

declare(strict_types=1);

namespace App\Modules\Booking\Api;

use App\Common\Api\ApiResponse;
use App\Common\Http\Request;
use App\Common\Security\ActorContext;
use App\Modules\Booking\Application\Dto\CreateBookingRequest;
use App\Modules\Booking\Application\Service\CreateBookingService;
use App\Platform\Idempotency\IdempotencyExecutor;

final class BookingController
{
    public function __construct(private CreateBookingService $service, private IdempotencyExecutor $idempotency)
    {
    }

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

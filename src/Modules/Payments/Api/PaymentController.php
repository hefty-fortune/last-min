<?php

declare(strict_types=1);

namespace App\Modules\Payments\Api;

use App\Common\Api\ApiResponse;
use App\Common\Http\Request;
use App\Common\Security\ActorContext;
use App\Modules\Payments\Application\Service\InitiatePaymentService;
use App\Platform\Idempotency\IdempotencyExecutor;

final class PaymentController
{
    public function __construct(private InitiatePaymentService $service, private IdempotencyExecutor $idempotency)
    {
    }

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
}

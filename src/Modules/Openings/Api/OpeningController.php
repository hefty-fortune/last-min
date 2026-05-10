<?php

declare(strict_types=1);

namespace App\Modules\Openings\Api;

use App\Common\Api\ApiResponse;
use App\Common\Http\Request;
use App\Common\Security\ActorContext;
use App\Modules\Openings\Application\Dto\CreateOpeningRequest;
use App\Modules\Openings\Application\Service\CancelOpeningService;
use App\Modules\Openings\Application\Service\CreateOpeningService;
use App\Modules\Openings\Application\Service\GetOpeningService;
use App\Modules\Openings\Application\Service\ListOpeningsService;
use App\Modules\Openings\Application\Service\PublishOpeningService;
use App\Platform\Idempotency\IdempotencyExecutor;

final class OpeningController
{
    public function __construct(
        private CreateOpeningService $createService,
        private GetOpeningService $getService,
        private ListOpeningsService $listService,
        private PublishOpeningService $publishService,
        private CancelOpeningService $cancelService,
        private IdempotencyExecutor $idempotency,
    ) {
    }

    public function create(ActorContext $actor, Request $request, string $providerId): ApiResponse
    {
        $key = $request->header('Idempotency-Key') ?? '';
        $payload = $request->body + ['provider_id' => $providerId];
        $result = $this->idempotency->execute('opening.create', $key, $payload, function () use ($actor, $request, $providerId): array {
            $data = $this->createService->create($actor, new CreateOpeningRequest(
                providerId: $providerId,
                serviceOfferingId: (string) $request->body['service_offering_id'],
                startsAt: (string) $request->body['starts_at'],
                endsAt: (string) $request->body['ends_at'],
                priceOverride: (array) ($request->body['price_override'] ?? []),
            ));

            return [
                'status' => 201,
                'body' => ['data' => $data, 'meta' => ['request_id' => uniqid('req_', true), 'idempotency_replayed' => false]],
                'resource_type' => 'opening',
                'resource_id' => $data['opening_id'],
            ];
        });

        return new ApiResponse($result['status'], $result['body']);
    }

    public function list(ActorContext $actor, Request $request, string $providerId): ApiResponse
    {
        $status = isset($request->attributes['query']['status']) ? (string) $request->attributes['query']['status'] : null;
        $limit = isset($request->attributes['query']['limit']) ? (int) $request->attributes['query']['limit'] : 50;
        $data = $this->listService->listForProvider($actor, $providerId, $status, $limit);

        return ApiResponse::ok(['data' => $data, 'meta' => ['request_id' => uniqid('req_', true)]]);
    }

    public function get(ActorContext $actor, string $providerId, string $openingId): ApiResponse
    {
        $data = $this->getService->getById($actor, $providerId, $openingId);

        return ApiResponse::ok(['data' => $data, 'meta' => ['request_id' => uniqid('req_', true)]]);
    }

    public function publish(ActorContext $actor, Request $request, string $providerId, string $openingId): ApiResponse
    {
        $key = $request->header('Idempotency-Key') ?? '';
        $payload = $request->body + ['provider_id' => $providerId, 'opening_id' => $openingId];
        $result = $this->idempotency->execute('opening.publish', $key, $payload, function () use ($actor, $providerId, $openingId): array {
            $data = $this->publishService->publish($actor, $providerId, $openingId);

            return [
                'status' => 200,
                'body' => ['data' => $data, 'meta' => ['request_id' => uniqid('req_', true), 'idempotency_replayed' => false]],
                'resource_type' => 'opening',
                'resource_id' => $data['opening_id'],
            ];
        });

        return new ApiResponse($result['status'], $result['body']);
    }

    public function cancel(ActorContext $actor, Request $request, string $providerId, string $openingId): ApiResponse
    {
        $key = $request->header('Idempotency-Key') ?? '';
        $payload = $request->body + ['provider_id' => $providerId, 'opening_id' => $openingId];
        $result = $this->idempotency->execute('opening.cancel', $key, $payload, function () use ($actor, $providerId, $openingId): array {
            $data = $this->cancelService->cancel($actor, $providerId, $openingId);

            return [
                'status' => 200,
                'body' => ['data' => $data, 'meta' => ['request_id' => uniqid('req_', true), 'idempotency_replayed' => false]],
                'resource_type' => 'opening',
                'resource_id' => $data['opening_id'],
            ];
        });

        return new ApiResponse($result['status'], $result['body']);
    }

    public function listPublic(Request $request): ApiResponse
    {
        $filters = [
            'provider_id' => $request->attributes['query']['provider_id'] ?? null,
            'service_offering_id' => $request->attributes['query']['service_offering_id'] ?? null,
            'starts_after' => $request->attributes['query']['starts_after'] ?? null,
            'starts_before' => $request->attributes['query']['starts_before'] ?? null,
            'max_price_minor' => $request->attributes['query']['max_price_minor'] ?? null,
        ];
        $limit = isset($request->attributes['query']['limit']) ? (int) $request->attributes['query']['limit'] : 50;
        $data = $this->listService->listPublic($filters, $limit);

        return ApiResponse::ok(['data' => $data, 'meta' => ['request_id' => uniqid('req_', true)]]);
    }
}

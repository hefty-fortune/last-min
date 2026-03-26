<?php

declare(strict_types=1);

namespace App\Modules\Openings\Api;

use App\Common\Api\ApiResponse;
use App\Common\Http\Request;
use App\Common\Security\ActorContext;
use App\Modules\Openings\Application\Dto\CreateOpeningRequest;
use App\Modules\Openings\Application\Service\CreateOpeningService;
use App\Platform\Idempotency\IdempotencyExecutor;

final class OpeningController
{
    public function __construct(private CreateOpeningService $service, private IdempotencyExecutor $idempotency)
    {
    }

    public function create(ActorContext $actor, Request $request, string $providerId): ApiResponse
    {
        $key = $request->header('Idempotency-Key') ?? '';
        $payload = $request->body + ['provider_id' => $providerId];
        $result = $this->idempotency->execute('opening.create', $key, $payload, function () use ($actor, $request, $providerId): array {
            $data = $this->service->create($actor, new CreateOpeningRequest(
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
}

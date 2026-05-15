<?php

declare(strict_types=1);

namespace App\Modules\Providers\Api;

use App\Common\Api\ApiResponse;
use App\Common\Http\Request;
use App\Common\Security\ActorContext;
use App\Modules\Providers\Application\Dto\CreateProviderRequest;
use App\Modules\Providers\Application\Service\CreateProviderService;
use App\Platform\Idempotency\IdempotencyExecutor;
use OpenApi\Attributes as OA;

final class ProviderController
{
    public function __construct(private CreateProviderService $service, private IdempotencyExecutor $idempotency)
    {
    }

    #[OA\Post(
        path: '/providers',
        summary: 'Create a provider (self-service)',
        security: [['bearerAuth' => []]],
        tags: ['Providers'],
        parameters: [
            new OA\Parameter(name: 'Idempotency-Key', in: 'header', required: true, schema: new OA\Schema(type: 'string')),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['provider_type'],
                properties: [
                    new OA\Property(property: 'provider_type', type: 'string'),
                ],
            ),
        ),
        responses: [
            new OA\Response(response: 201, description: 'Provider created'),
        ],
    )]
    public function create(ActorContext $actor, Request $request): ApiResponse
    {
        $idempotencyKey = $request->header('Idempotency-Key') ?? '';
        $result = $this->idempotency->execute('provider.create', $idempotencyKey, $request->body, function () use ($actor, $request): array {
            $data = $this->service->create($actor, new CreateProviderRequest((string) ($request->body['provider_type'] ?? '')));
            return [
                'status' => 201,
                'body' => ['data' => $data, 'meta' => ['request_id' => uniqid('req_', true), 'idempotency_replayed' => false]],
                'resource_type' => 'provider',
                'resource_id' => $data['provider_id'],
            ];
        });

        return new ApiResponse($result['status'], $result['body']);
    }
}

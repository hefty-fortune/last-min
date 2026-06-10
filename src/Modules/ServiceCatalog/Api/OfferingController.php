<?php

declare(strict_types=1);

namespace App\Modules\ServiceCatalog\Api;

use App\Common\Api\ApiResponse;
use App\Common\Http\Request;
use App\Common\Security\ActorContext;
use App\Modules\ServiceCatalog\Application\Service\CreateOfferingService;
use App\Modules\ServiceCatalog\Application\Service\ListOfferingsService;
use App\Modules\ServiceCatalog\Application\Service\UpdateOfferingService;
use App\Platform\Idempotency\IdempotencyExecutor;
use OpenApi\Attributes as OA;

final class OfferingController
{
    public function __construct(
        private CreateOfferingService $createService,
        private ListOfferingsService $listService,
        private UpdateOfferingService $updateService,
        private IdempotencyExecutor $idempotency,
    ) {
    }

    #[OA\Post(
        path: '/providers/{provider_id}/offerings',
        summary: 'Create a service offering for a provider',
        security: [['apiKey' => []]],
        tags: ['ServiceCatalog'],
        parameters: [
            new OA\Parameter(name: 'provider_id', in: 'path', required: true, schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'Idempotency-Key', in: 'header', required: true, schema: new OA\Schema(type: 'string')),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['name', 'duration_minutes', 'base_price'],
                properties: [
                    new OA\Property(property: 'name', type: 'string'),
                    new OA\Property(property: 'description', type: 'string', nullable: true),
                    new OA\Property(property: 'duration_minutes', type: 'integer', minimum: 5),
                    new OA\Property(property: 'base_price', type: 'object'),
                ],
            ),
        ),
        responses: [
            new OA\Response(response: 201, description: 'Offering created'),
            new OA\Response(response: 403, description: 'Forbidden', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
            new OA\Response(response: 422, description: 'Validation failure', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
        ],
    )]
    public function create(ActorContext $actor, Request $request, string $providerId): ApiResponse
    {
        $key = $request->header('Idempotency-Key') ?? '';
        $payload = $request->body + ['provider_id' => $providerId];
        $result = $this->idempotency->execute('offering.create', $key, $payload, function () use ($actor, $request, $providerId): array {
            $data = $this->createService->create($actor, $providerId, $request->body);

            return [
                'status' => 201,
                'body' => ['data' => $data, 'meta' => ['request_id' => uniqid('req_', true), 'idempotency_replayed' => false]],
                'resource_type' => 'offering',
                'resource_id' => $data['offering_id'],
            ];
        });

        return new ApiResponse($result['status'], $result['body']);
    }

    #[OA\Get(
        path: '/providers/{provider_id}/offerings',
        summary: 'List offerings for a provider',
        security: [['apiKey' => []]],
        tags: ['ServiceCatalog'],
        parameters: [
            new OA\Parameter(name: 'provider_id', in: 'path', required: true, schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'status', in: 'query', required: false, schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'limit', in: 'query', required: false, schema: new OA\Schema(type: 'integer', default: 50)),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Offerings for the provider'),
            new OA\Response(response: 403, description: 'Forbidden', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
        ],
    )]
    public function list(ActorContext $actor, Request $request, string $providerId): ApiResponse
    {
        $status = isset($request->attributes['query']['status']) ? (string) $request->attributes['query']['status'] : null;
        $limit = isset($request->attributes['query']['limit']) ? (int) $request->attributes['query']['limit'] : 50;
        $data = $this->listService->listForProvider($actor, $providerId, $status, $limit);

        return ApiResponse::ok(['data' => $data, 'meta' => ['request_id' => uniqid('req_', true)]]);
    }

    #[OA\Patch(
        path: '/providers/{provider_id}/offerings/{offering_id}',
        summary: 'Update a service offering',
        security: [['apiKey' => []]],
        tags: ['ServiceCatalog'],
        parameters: [
            new OA\Parameter(name: 'provider_id', in: 'path', required: true, schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'offering_id', in: 'path', required: true, schema: new OA\Schema(type: 'string')),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'name', type: 'string'),
                    new OA\Property(property: 'description', type: 'string', nullable: true),
                    new OA\Property(property: 'duration_minutes', type: 'integer', minimum: 5),
                    new OA\Property(property: 'base_price', type: 'object'),
                    new OA\Property(property: 'status', type: 'string', enum: ['active', 'inactive']),
                ],
            ),
        ),
        responses: [
            new OA\Response(response: 200, description: 'Offering updated'),
            new OA\Response(response: 403, description: 'Forbidden', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
            new OA\Response(response: 404, description: 'Not found', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
            new OA\Response(response: 422, description: 'Validation failure', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
        ],
    )]
    public function update(ActorContext $actor, Request $request, string $providerId, string $offeringId): ApiResponse
    {
        $data = $this->updateService->update($actor, $providerId, $offeringId, $request->body);

        return ApiResponse::ok(['data' => $data, 'meta' => ['request_id' => uniqid('req_', true)]]);
    }

    #[OA\Get(
        path: '/public/providers/{provider_id}/offerings',
        summary: 'Publicly list active offerings for a provider',
        tags: ['ServiceCatalog'],
        parameters: [
            new OA\Parameter(name: 'provider_id', in: 'path', required: true, schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'limit', in: 'query', required: false, schema: new OA\Schema(type: 'integer', default: 50)),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Active offerings for the provider'),
        ],
    )]
    public function listPublic(Request $request, string $providerId): ApiResponse
    {
        $limit = isset($request->attributes['query']['limit']) ? (int) $request->attributes['query']['limit'] : 50;
        $data = $this->listService->listPublic($providerId, $limit);

        return ApiResponse::ok(['data' => $data, 'meta' => ['request_id' => uniqid('req_', true)]]);
    }
}

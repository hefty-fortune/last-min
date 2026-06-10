<?php

declare(strict_types=1);

namespace App\Modules\Providers\Api;

use App\Common\Api\ApiResponse;
use App\Common\Http\Request;
use App\Common\Security\ActorContext;
use App\Modules\Providers\Application\Dto\CreateProviderRequest;
use App\Modules\Providers\Application\Service\CreateProviderService;
use App\Modules\Providers\Application\Service\GetProviderProfileService;
use App\Modules\Providers\Application\Service\LinkProviderService;
use App\Modules\Providers\Application\Service\UpdateProviderService;
use App\Platform\Idempotency\IdempotencyExecutor;
use OpenApi\Attributes as OA;

final class ProviderController
{
    public function __construct(
        private CreateProviderService $service,
        private GetProviderProfileService $getService,
        private UpdateProviderService $updateService,
        private LinkProviderService $linkService,
        private IdempotencyExecutor $idempotency,
    ) {
    }

    #[OA\Post(
        path: '/providers',
        summary: 'Create a provider (self-service)',
        security: [['apiKey' => []]],
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
            new OA\Response(response: 201, description: 'Provider created', content: new OA\JsonContent(ref: '#/components/schemas/ProviderCreatedResponse')),
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

    #[OA\Get(
        path: '/providers/{provider_id}',
        summary: 'Get provider profile for owner or admin',
        security: [['apiKey' => []]],
        tags: ['Providers'],
        parameters: [
            new OA\Parameter(name: 'provider_id', in: 'path', required: true, schema: new OA\Schema(type: 'string')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Provider profile'),
            new OA\Response(response: 403, description: 'Forbidden', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
            new OA\Response(response: 404, description: 'Not found', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
        ],
    )]
    public function get(ActorContext $actor, string $providerId): ApiResponse
    {
        $data = $this->getService->getById($actor, $providerId);

        return ApiResponse::ok(['data' => $data, 'meta' => ['request_id' => uniqid('req_', true)]]);
    }

    #[OA\Patch(
        path: '/providers/{provider_id}',
        summary: 'Update provider profile (owner) or status (admin)',
        security: [['apiKey' => []]],
        tags: ['Providers'],
        parameters: [
            new OA\Parameter(name: 'provider_id', in: 'path', required: true, schema: new OA\Schema(type: 'string')),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'display_name', type: 'string'),
                    new OA\Property(property: 'status', type: 'string', enum: ['onboarding', 'active', 'suspended']),
                ],
            ),
        ),
        responses: [
            new OA\Response(response: 200, description: 'Provider updated'),
            new OA\Response(response: 403, description: 'Forbidden', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
            new OA\Response(response: 422, description: 'Validation failure', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
        ],
    )]
    public function update(ActorContext $actor, Request $request, string $providerId): ApiResponse
    {
        $data = $this->updateService->update($actor, $providerId, $request->body);

        return ApiResponse::ok(['data' => $data, 'meta' => ['request_id' => uniqid('req_', true)]]);
    }

    #[OA\Post(
        path: '/me/provider-link',
        summary: 'Link the authenticated actor to an individual provider profile',
        security: [['apiKey' => []]],
        tags: ['Providers'],
        parameters: [
            new OA\Parameter(name: 'Idempotency-Key', in: 'header', required: true, schema: new OA\Schema(type: 'string')),
        ],
        requestBody: new OA\RequestBody(
            required: false,
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'provider_type', type: 'string', enum: ['individual']),
                    new OA\Property(property: 'display_name', type: 'string', nullable: true),
                ],
            ),
        ),
        responses: [
            new OA\Response(response: 201, description: 'Provider linked'),
            new OA\Response(response: 409, description: 'Already linked', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
        ],
    )]
    public function link(ActorContext $actor, Request $request): ApiResponse
    {
        $key = $request->header('Idempotency-Key') ?? '';
        $result = $this->idempotency->execute('provider.link', $key, $request->body, function () use ($actor, $request): array {
            $data = $this->linkService->link($actor, $request->body);

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

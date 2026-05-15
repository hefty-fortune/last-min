<?php

declare(strict_types=1);

namespace App\Modules\AdminSetup\Api;

use App\Common\Api\ApiResponse;
use App\Common\Http\Request;
use App\Common\Security\ActorContext;
use App\Modules\AdminSetup\Application\Dto\CreateProviderRequest;
use App\Modules\AdminSetup\Application\Service\CreateProviderService;
use App\Modules\AdminSetup\Application\Service\GetProviderService;
use App\Modules\AdminSetup\Application\Service\ListProvidersService;
use OpenApi\Attributes as OA;

final class ProviderAdminController
{
    public function __construct(
        private CreateProviderService $service,
        private GetProviderService $getService,
        private ListProvidersService $listService,
    ) {
    }

    #[OA\Post(
        path: '/admin/providers',
        summary: 'Create a provider',
        security: [['apiKey' => []]],
        tags: ['Admin - Providers'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['organization_id', 'display_name', 'status'],
                properties: [
                    new OA\Property(property: 'organization_id', type: 'string'),
                    new OA\Property(property: 'display_name', type: 'string'),
                    new OA\Property(property: 'status', type: 'string'),
                ],
            ),
        ),
        responses: [
            new OA\Response(response: 201, description: 'Provider created', content: new OA\JsonContent(ref: '#/components/schemas/AdminProviderCreatedResponse')),
            new OA\Response(response: 403, description: 'Forbidden', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
        ],
    )]
    public function create(ActorContext $actor, Request $request): ApiResponse
    {
        $data = $this->service->create($actor, new CreateProviderRequest(
            organizationId: (string) ($request->body['organization_id'] ?? ''),
            displayName: (string) ($request->body['display_name'] ?? ''),
            status: (string) ($request->body['status'] ?? ''),
        ));

        return ApiResponse::created(['data' => $data, 'meta' => ['request_id' => uniqid('req_', true)]]);
    }

    #[OA\Get(
        path: '/admin/providers',
        summary: 'List providers',
        security: [['apiKey' => []]],
        tags: ['Admin - Providers'],
        parameters: [
            new OA\Parameter(name: 'organization_id', in: 'query', required: false, schema: new OA\Schema(type: 'string')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'List of providers', content: new OA\JsonContent(ref: '#/components/schemas/AdminProviderListResponse')),
        ],
    )]
    public function list(ActorContext $actor, Request $request): ApiResponse
    {
        $organizationId = isset($request->attributes['query']['organization_id'])
            ? (string) $request->attributes['query']['organization_id']
            : null;
        $data = $this->listService->list($actor, $organizationId);

        return ApiResponse::ok(['data' => $data, 'meta' => ['request_id' => uniqid('req_', true)]]);
    }

    #[OA\Get(
        path: '/admin/providers/{provider_id}',
        summary: 'Get a provider by ID',
        security: [['apiKey' => []]],
        tags: ['Admin - Providers'],
        parameters: [
            new OA\Parameter(name: 'provider_id', in: 'path', required: true, schema: new OA\Schema(type: 'string')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Provider details', content: new OA\JsonContent(ref: '#/components/schemas/AdminProviderResponse')),
            new OA\Response(response: 404, description: 'Not found', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
        ],
    )]
    public function get(ActorContext $actor, string $providerId): ApiResponse
    {
        $data = $this->getService->getById($actor, $providerId);

        return ApiResponse::ok(['data' => $data, 'meta' => ['request_id' => uniqid('req_', true)]]);
    }
}

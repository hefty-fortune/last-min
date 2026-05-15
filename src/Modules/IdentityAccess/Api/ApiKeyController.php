<?php

declare(strict_types=1);

namespace App\Modules\IdentityAccess\Api;

use App\Common\Api\ApiResponse;
use App\Common\Http\Request;
use App\Common\Security\ActorContext;
use App\Modules\IdentityAccess\Application\Dto\CreateApiKeyRequest;
use App\Modules\IdentityAccess\Application\Service\CreateApiKeyService;
use App\Modules\IdentityAccess\Application\Service\DeleteApiKeyService;
use App\Modules\IdentityAccess\Application\Service\ListApiKeysService;
use OpenApi\Attributes as OA;

final class ApiKeyController
{
    public function __construct(
        private CreateApiKeyService $createService,
        private DeleteApiKeyService $deleteService,
        private ListApiKeysService $listService,
    ) {
    }

    #[OA\Post(
        path: '/api-key',
        summary: 'Create an API key',
        security: [['bearerAuth' => []]],
        tags: ['API Keys'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['client_id'],
                properties: [
                    new OA\Property(property: 'client_id', type: 'string'),
                    new OA\Property(property: 'name', type: 'string', default: 'Default API key'),
                ],
            ),
        ),
        responses: [
            new OA\Response(response: 201, description: 'API key created'),
        ],
    )]
    public function create(ActorContext $actor, Request $request): ApiResponse
    {
        $data = $this->createService->create($actor, new CreateApiKeyRequest(
            clientId: (string) ($request->body['client_id'] ?? ''),
            name: (string) ($request->body['name'] ?? 'Default API key'),
        ));

        return ApiResponse::created(['data' => $data, 'meta' => ['request_id' => uniqid('req_', true)]]);
    }

    #[OA\Get(
        path: '/api-keys',
        summary: 'List API keys',
        security: [['bearerAuth' => []]],
        tags: ['API Keys'],
        parameters: [
            new OA\Parameter(name: 'client_id', in: 'query', required: true, schema: new OA\Schema(type: 'string')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'List of API keys'),
        ],
    )]
    public function list(ActorContext $actor, Request $request): ApiResponse
    {
        $clientId = (string) ($request->attributes['query']['client_id'] ?? '');
        $data = $this->listService->list($actor, $clientId);

        return ApiResponse::ok(['data' => $data, 'meta' => ['request_id' => uniqid('req_', true)]]);
    }

    #[OA\Delete(
        path: '/api-key/{api_key_id}',
        summary: 'Delete an API key',
        security: [['bearerAuth' => []]],
        tags: ['API Keys'],
        parameters: [
            new OA\Parameter(name: 'api_key_id', in: 'path', required: true, schema: new OA\Schema(type: 'string')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'API key revoked'),
        ],
    )]
    public function delete(ActorContext $actor, string $apiKeyId): ApiResponse
    {
        $this->deleteService->delete($actor, $apiKeyId);

        return ApiResponse::ok(['data' => ['api_key_id' => $apiKeyId, 'revoked' => true], 'meta' => ['request_id' => uniqid('req_', true)]]);
    }
}

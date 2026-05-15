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
        security: [['apiKey' => []]],
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
            new OA\Response(response: 201, description: 'API key created', content: new OA\JsonContent(ref: '#/components/schemas/ApiKeyCreatedResponse')),
        ],
    )]
    public function create(ActorContext $actor, Request $request): ApiResponse
    {
        $data = $this->createService->create($actor, new CreateApiKeyRequest(
            name: (string) ($request->body['name'] ?? ''),
        ));

        return ApiResponse::created(['data' => $data, 'meta' => ['request_id' => uniqid('req_', true)]]);
    }

    #[OA\Get(
        path: '/api-keys',
        summary: 'List API keys',
        security: [['apiKey' => []]],
        tags: ['API Keys'],
        responses: [
            new OA\Response(response: 200, description: 'List of API keys', content: new OA\JsonContent(ref: '#/components/schemas/ApiKeyListResponse')),
        ],
    )]
    public function list(ActorContext $actor): ApiResponse
    {
        $data = $this->listService->list($actor);

        return ApiResponse::ok(['data' => $data, 'meta' => ['request_id' => uniqid('req_', true)]]);
    }

    #[OA\Delete(
        path: '/api-key/{api_key_id}',
        summary: 'Delete an API key',
        security: [['apiKey' => []]],
        tags: ['API Keys'],
        parameters: [
            new OA\Parameter(name: 'api_key_id', in: 'path', required: true, schema: new OA\Schema(type: 'string')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'API key revoked', content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'data', properties: [
                        new OA\Property(property: 'api_key_id', type: 'string'),
                        new OA\Property(property: 'revoked', type: 'boolean'),
                    ], type: 'object'),
                    new OA\Property(property: 'meta', ref: '#/components/schemas/Meta'),
                ],
            )),
        ],
    )]
    public function delete(ActorContext $actor, string $apiKeyId): ApiResponse
    {
        $this->deleteService->delete($actor, $apiKeyId);

        return ApiResponse::ok(['data' => ['api_key_id' => $apiKeyId, 'revoked' => true], 'meta' => ['request_id' => uniqid('req_', true)]]);
    }

    public function destroy(ActorContext $actor, string $apiKeyId): ApiResponse
    {
        $this->deleteService->destroy($actor, $apiKeyId);

        return ApiResponse::ok(['data' => ['api_key_id' => $apiKeyId, 'deleted' => true], 'meta' => ['request_id' => uniqid('req_', true)]]);
    }
}

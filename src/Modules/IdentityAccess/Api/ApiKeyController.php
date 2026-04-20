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

final class ApiKeyController
{
    public function __construct(
        private CreateApiKeyService $createService,
        private DeleteApiKeyService $deleteService,
        private ListApiKeysService $listService,
    ) {
    }

    public function create(ActorContext $actor, Request $request): ApiResponse
    {
        $data = $this->createService->create($actor, new CreateApiKeyRequest(
            clientId: (string) ($request->body['client_id'] ?? ''),
            name: (string) ($request->body['name'] ?? 'Default API key'),
        ));

        return ApiResponse::created(['data' => $data, 'meta' => ['request_id' => uniqid('req_', true)]]);
    }

    public function list(ActorContext $actor, Request $request): ApiResponse
    {
        $clientId = (string) ($request->attributes['query']['client_id'] ?? '');
        $data = $this->listService->list($actor, $clientId);

        return ApiResponse::ok(['data' => $data, 'meta' => ['request_id' => uniqid('req_', true)]]);
    }

    public function delete(ActorContext $actor, string $apiKeyId): ApiResponse
    {
        $this->deleteService->delete($actor, $apiKeyId);

        return ApiResponse::ok(['data' => ['api_key_id' => $apiKeyId, 'revoked' => true], 'meta' => ['request_id' => uniqid('req_', true)]]);
    }
}

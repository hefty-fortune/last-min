<?php

declare(strict_types=1);

namespace App\Modules\IdentityAccess\Api;

use App\Common\Api\ApiResponse;
use App\Common\Http\Request;
use App\Common\Security\ActorContext;
use App\Modules\IdentityAccess\Application\Dto\CreateApiKeyRequest;
use App\Modules\IdentityAccess\Application\Service\CreateApiKeyService;
use App\Modules\IdentityAccess\Application\Service\DeleteApiKeyService;

final class ApiKeyController
{
    public function __construct(
        private CreateApiKeyService $createService,
        private DeleteApiKeyService $deleteService,
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

    public function delete(ActorContext $actor, Request $request): ApiResponse
    {
        $clientId = (string) ($request->attributes['query']['client_id'] ?? '');
        $this->deleteService->delete($actor, $clientId);

        return ApiResponse::ok(['data' => ['client_id' => $clientId, 'deleted' => true], 'meta' => ['request_id' => uniqid('req_', true)]]);
    }
}

<?php

declare(strict_types=1);

namespace App\Modules\AdminSetup\Api;

use App\Common\Api\ApiResponse;
use App\Common\Http\Request;
use App\Common\Security\ActorContext;
use App\Modules\AdminSetup\Application\Dto\CreateProviderRequest;
use App\Modules\AdminSetup\Application\Service\CreateProviderService;
use App\Modules\AdminSetup\Application\Service\ListProvidersService;

final class ProviderAdminController
{
    public function __construct(
        private CreateProviderService $service,
        private ListProvidersService $listService,
    ) {
    }

    public function create(ActorContext $actor, Request $request): ApiResponse
    {
        $data = $this->service->create($actor, new CreateProviderRequest(
            organizationId: (string) ($request->body['organization_id'] ?? ''),
            displayName: (string) ($request->body['display_name'] ?? ''),
            status: (string) ($request->body['status'] ?? ''),
        ));

        return ApiResponse::created(['data' => $data, 'meta' => ['request_id' => uniqid('req_', true)]]);
    }

    public function list(ActorContext $actor, Request $request): ApiResponse
    {
        $organizationId = isset($request->attributes['query']['organization_id'])
            ? (string) $request->attributes['query']['organization_id']
            : null;
        $data = $this->listService->list($actor, $organizationId);

        return ApiResponse::ok(['data' => $data, 'meta' => ['request_id' => uniqid('req_', true)]]);
    }
}

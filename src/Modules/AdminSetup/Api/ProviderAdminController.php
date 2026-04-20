<?php

declare(strict_types=1);

namespace App\Modules\AdminSetup\Api;

use App\Common\Api\ApiResponse;
use App\Common\Http\Request;
use App\Common\Security\ActorContext;
use App\Modules\AdminSetup\Application\Dto\CreateProviderRequest;
use App\Modules\AdminSetup\Application\Service\CreateProviderService;

final class ProviderAdminController
{
    public function __construct(private CreateProviderService $service)
    {
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
}

<?php

declare(strict_types=1);

namespace App\Modules\AdminSetup\Api;

use App\Common\Api\ApiResponse;
use App\Common\Http\Request;
use App\Common\Security\ActorContext;
use App\Modules\AdminSetup\Application\Dto\CreateOrganizationRequest;
use App\Modules\AdminSetup\Application\Service\CreateOrganizationService;

final class OrganizationAdminController
{
    public function __construct(private CreateOrganizationService $service)
    {
    }

    public function create(ActorContext $actor, Request $request): ApiResponse
    {
        $data = $this->service->create($actor, new CreateOrganizationRequest(
            legalName: (string) ($request->body['legal_name'] ?? ''),
            displayName: (string) ($request->body['display_name'] ?? ''),
            taxId: isset($request->body['tax_id']) ? (string) $request->body['tax_id'] : null,
            contactEmail: (string) ($request->body['contact_email'] ?? ''),
            contactPhone: (string) ($request->body['contact_phone'] ?? ''),
        ));

        return ApiResponse::created(['data' => $data, 'meta' => ['request_id' => uniqid('req_', true)]]);
    }
}

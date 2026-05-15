<?php

declare(strict_types=1);

namespace App\Modules\AdminSetup\Api;

use App\Common\Api\ApiResponse;
use App\Common\Http\Request;
use App\Common\Security\ActorContext;
use App\Modules\AdminSetup\Application\Dto\CreateOrganizationRequest;
use App\Modules\AdminSetup\Application\Service\CreateOrganizationService;
use App\Modules\AdminSetup\Application\Service\GetOrganizationService;
use App\Modules\AdminSetup\Application\Service\ListOrganizationsService;
use OpenApi\Attributes as OA;

final class OrganizationAdminController
{
    public function __construct(
        private CreateOrganizationService $service,
        private GetOrganizationService $getService,
        private ListOrganizationsService $listService,
    ) {
    }

    #[OA\Post(
        path: '/admin/organizations',
        summary: 'Create an organization',
        security: [['bearerAuth' => []]],
        tags: ['Admin - Organizations'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['legal_name', 'display_name', 'contact_email', 'contact_phone'],
                properties: [
                    new OA\Property(property: 'legal_name', type: 'string'),
                    new OA\Property(property: 'display_name', type: 'string'),
                    new OA\Property(property: 'tax_id', type: 'string', nullable: true),
                    new OA\Property(property: 'contact_email', type: 'string', format: 'email'),
                    new OA\Property(property: 'contact_phone', type: 'string'),
                ],
            ),
        ),
        responses: [
            new OA\Response(response: 201, description: 'Organization created', content: new OA\JsonContent(ref: '#/components/schemas/OrganizationCreatedResponse')),
            new OA\Response(response: 403, description: 'Forbidden', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
        ],
    )]
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

    #[OA\Get(
        path: '/admin/organizations',
        summary: 'List organizations',
        security: [['bearerAuth' => []]],
        tags: ['Admin - Organizations'],
        responses: [
            new OA\Response(response: 200, description: 'List of organizations', content: new OA\JsonContent(ref: '#/components/schemas/OrganizationListResponse')),
        ],
    )]
    public function list(ActorContext $actor): ApiResponse
    {
        $data = $this->listService->list($actor);

        return ApiResponse::ok(['data' => $data, 'meta' => ['request_id' => uniqid('req_', true)]]);
    }

    #[OA\Get(
        path: '/admin/organizations/{organization_id}',
        summary: 'Get an organization by ID',
        security: [['bearerAuth' => []]],
        tags: ['Admin - Organizations'],
        parameters: [
            new OA\Parameter(name: 'organization_id', in: 'path', required: true, schema: new OA\Schema(type: 'string')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Organization details', content: new OA\JsonContent(ref: '#/components/schemas/OrganizationResponse')),
            new OA\Response(response: 404, description: 'Not found', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
        ],
    )]
    public function get(ActorContext $actor, string $organizationId): ApiResponse
    {
        $data = $this->getService->getById($actor, $organizationId);

        return ApiResponse::ok(['data' => $data, 'meta' => ['request_id' => uniqid('req_', true)]]);
    }
}

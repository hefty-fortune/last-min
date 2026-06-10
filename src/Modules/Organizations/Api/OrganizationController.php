<?php

declare(strict_types=1);

namespace App\Modules\Organizations\Api;

use App\Common\Api\ApiResponse;
use App\Common\Http\Request;
use App\Common\Security\ActorContext;
use App\Modules\Organizations\Application\Service\AddOrganizationMemberService;
use App\Modules\Organizations\Application\Service\CreateOrganizationSelfService;
use App\Modules\Organizations\Application\Service\ViewOrganizationService;
use App\Platform\Idempotency\IdempotencyExecutor;
use OpenApi\Attributes as OA;

final class OrganizationController
{
    public function __construct(
        private CreateOrganizationSelfService $createService,
        private ViewOrganizationService $viewService,
        private AddOrganizationMemberService $addMemberService,
        private IdempotencyExecutor $idempotency,
    ) {
    }

    #[OA\Post(
        path: '/organizations',
        summary: 'Create an organization (provider self-service)',
        security: [['apiKey' => []]],
        tags: ['Organizations'],
        parameters: [
            new OA\Parameter(name: 'Idempotency-Key', in: 'header', required: true, schema: new OA\Schema(type: 'string')),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['legal_name', 'display_name', 'contact_email', 'contact_phone'],
                properties: [
                    new OA\Property(property: 'legal_name', type: 'string'),
                    new OA\Property(property: 'display_name', type: 'string'),
                    new OA\Property(property: 'tax_id', type: 'string', nullable: true),
                    new OA\Property(property: 'contact_email', type: 'string'),
                    new OA\Property(property: 'contact_phone', type: 'string'),
                ],
            ),
        ),
        responses: [
            new OA\Response(response: 201, description: 'Organization created with creator as owner member'),
            new OA\Response(response: 403, description: 'Forbidden', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
            new OA\Response(response: 409, description: 'Conflict', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
            new OA\Response(response: 422, description: 'Validation failure', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
        ],
    )]
    public function create(ActorContext $actor, Request $request): ApiResponse
    {
        $key = $request->header('Idempotency-Key') ?? '';
        $result = $this->idempotency->execute('organization.create', $key, $request->body, function () use ($actor, $request): array {
            $data = $this->createService->create($actor, $request->body);

            return [
                'status' => 201,
                'body' => ['data' => $data, 'meta' => ['request_id' => uniqid('req_', true), 'idempotency_replayed' => false]],
                'resource_type' => 'organization',
                'resource_id' => $data['organization_id'],
            ];
        });

        return new ApiResponse($result['status'], $result['body']);
    }

    #[OA\Get(
        path: '/organizations/{organization_id}',
        summary: 'Get organization details for members and admins',
        security: [['apiKey' => []]],
        tags: ['Organizations'],
        parameters: [
            new OA\Parameter(name: 'organization_id', in: 'path', required: true, schema: new OA\Schema(type: 'string')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Organization with members'),
            new OA\Response(response: 403, description: 'Forbidden', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
            new OA\Response(response: 404, description: 'Not found', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
        ],
    )]
    public function get(ActorContext $actor, string $organizationId): ApiResponse
    {
        $data = $this->viewService->getById($actor, $organizationId);

        return ApiResponse::ok(['data' => $data, 'meta' => ['request_id' => uniqid('req_', true)]]);
    }

    #[OA\Post(
        path: '/organizations/{organization_id}/members',
        summary: 'Add a member to an organization',
        security: [['apiKey' => []]],
        tags: ['Organizations'],
        parameters: [
            new OA\Parameter(name: 'organization_id', in: 'path', required: true, schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'Idempotency-Key', in: 'header', required: true, schema: new OA\Schema(type: 'string')),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['user_profile_id', 'organization_role'],
                properties: [
                    new OA\Property(property: 'user_profile_id', type: 'string'),
                    new OA\Property(property: 'organization_role', type: 'string', enum: ['owner', 'manager', 'staff']),
                ],
            ),
        ),
        responses: [
            new OA\Response(response: 201, description: 'Member added'),
            new OA\Response(response: 403, description: 'Forbidden', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
            new OA\Response(response: 409, description: 'Member already exists', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
        ],
    )]
    public function addMember(ActorContext $actor, Request $request, string $organizationId): ApiResponse
    {
        $key = $request->header('Idempotency-Key') ?? '';
        $payload = $request->body + ['organization_id' => $organizationId];
        $result = $this->idempotency->execute('organization.member.add', $key, $payload, function () use ($actor, $request, $organizationId): array {
            $data = $this->addMemberService->addMember($actor, $organizationId, $request->body);

            return [
                'status' => 201,
                'body' => ['data' => $data, 'meta' => ['request_id' => uniqid('req_', true), 'idempotency_replayed' => false]],
                'resource_type' => 'organization_member',
                'resource_id' => $data['member_id'],
            ];
        });

        return new ApiResponse($result['status'], $result['body']);
    }
}

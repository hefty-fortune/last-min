<?php

declare(strict_types=1);

namespace App\Modules\AdminSetup\Api;

use App\Common\Api\ApiResponse;
use App\Common\Http\Request;
use App\Common\Security\ActorContext;
use App\Modules\AdminSetup\Application\Dto\CreateUserRequest;
use App\Modules\AdminSetup\Application\Service\CreateUserService;
use App\Modules\AdminSetup\Application\Service\GetUserService;
use App\Modules\AdminSetup\Application\Service\ListUsersService;
use App\Modules\AdminSetup\Application\Service\ResetUserPasswordService;
use App\Modules\AdminSetup\Application\Service\UpdateUserRolesService;
use App\Modules\AdminSetup\Application\Service\UpdateUserService;
use OpenApi\Attributes as OA;

final class UserAdminController
{
    public function __construct(
        private CreateUserService $service,
        private GetUserService $getService,
        private ListUsersService $listService,
        private UpdateUserService $updateService,
        private UpdateUserRolesService $updateRolesService,
        private ResetUserPasswordService $resetPasswordService,
    ) {
    }

    #[OA\Post(
        path: '/admin/users',
        summary: 'Create a user',
        security: [['bearerAuth' => []]],
        tags: ['Admin - Users'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['first_name', 'last_name', 'email', 'phone', 'provider_id'],
                properties: [
                    new OA\Property(property: 'first_name', type: 'string'),
                    new OA\Property(property: 'last_name', type: 'string'),
                    new OA\Property(property: 'email', type: 'string', format: 'email'),
                    new OA\Property(property: 'phone', type: 'string'),
                    new OA\Property(property: 'roles', type: 'array', items: new OA\Items(type: 'string')),
                    new OA\Property(property: 'provider_id', type: 'string'),
                    new OA\Property(property: 'password', type: 'string', nullable: true),
                ],
            ),
        ),
        responses: [
            new OA\Response(response: 201, description: 'User created', content: new OA\JsonContent(ref: '#/components/schemas/UserCreatedResponse')),
            new OA\Response(response: 403, description: 'Forbidden', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
        ],
    )]
    public function create(ActorContext $actor, Request $request): ApiResponse
    {
        $roles = $request->body['roles'] ?? [];
        $password = isset($request->body['password']) && is_string($request->body['password']) && trim($request->body['password']) !== ''
            ? (string) $request->body['password']
            : null;
        $data = $this->service->create($actor, new CreateUserRequest(
            firstName: (string) ($request->body['first_name'] ?? ''),
            lastName: (string) ($request->body['last_name'] ?? ''),
            email: (string) ($request->body['email'] ?? ''),
            phone: (string) ($request->body['phone'] ?? ''),
            roles: is_array($roles) ? array_values($roles) : [],
            providerId: (string) ($request->body['provider_id'] ?? ''),
            password: $password,
        ));

        return ApiResponse::created(['data' => $data, 'meta' => ['request_id' => uniqid('req_', true)]]);
    }

    #[OA\Get(
        path: '/admin/users',
        summary: 'List users',
        security: [['bearerAuth' => []]],
        tags: ['Admin - Users'],
        parameters: [
            new OA\Parameter(name: 'provider_id', in: 'query', required: false, schema: new OA\Schema(type: 'string')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'List of users', content: new OA\JsonContent(ref: '#/components/schemas/UserListResponse')),
        ],
    )]
    public function list(ActorContext $actor, Request $request): ApiResponse
    {
        $providerId = isset($request->attributes['query']['provider_id'])
            ? (string) $request->attributes['query']['provider_id']
            : null;
        $data = $this->listService->list($actor, $providerId);

        return ApiResponse::ok(['data' => $data, 'meta' => ['request_id' => uniqid('req_', true)]]);
    }

    #[OA\Get(
        path: '/admin/users/{user_id}',
        summary: 'Get a user by ID',
        security: [['bearerAuth' => []]],
        tags: ['Admin - Users'],
        parameters: [
            new OA\Parameter(name: 'user_id', in: 'path', required: true, schema: new OA\Schema(type: 'string')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'User details', content: new OA\JsonContent(ref: '#/components/schemas/UserResponse')),
            new OA\Response(response: 404, description: 'Not found', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
        ],
    )]
    public function get(ActorContext $actor, string $userId): ApiResponse
    {
        $data = $this->getService->getById($actor, $userId);

        return ApiResponse::ok(['data' => $data, 'meta' => ['request_id' => uniqid('req_', true)]]);
    }

    #[OA\Patch(
        path: '/admin/users/{user_id}',
        summary: 'Update user details',
        security: [['bearerAuth' => []]],
        tags: ['Admin - Users'],
        parameters: [
            new OA\Parameter(name: 'user_id', in: 'path', required: true, schema: new OA\Schema(type: 'string')),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'first_name', type: 'string'),
                    new OA\Property(property: 'last_name', type: 'string'),
                    new OA\Property(property: 'email', type: 'string', format: 'email'),
                    new OA\Property(property: 'phone', type: 'string'),
                ],
            ),
        ),
        responses: [
            new OA\Response(response: 200, description: 'User updated', content: new OA\JsonContent(ref: '#/components/schemas/UserResponse')),
            new OA\Response(response: 404, description: 'Not found', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
        ],
    )]
    public function update(ActorContext $actor, Request $request, string $userId): ApiResponse
    {
        $fields = [];
        foreach (['first_name', 'last_name', 'email', 'phone'] as $field) {
            if (array_key_exists($field, $request->body)) {
                $fields[$field] = $request->body[$field];
            }
        }

        $data = $this->updateService->update($actor, $userId, $fields);

        return ApiResponse::ok(['data' => $data, 'meta' => ['request_id' => uniqid('req_', true)]]);
    }

    #[OA\Patch(
        path: '/admin/users/{user_id}/roles',
        summary: 'Update user roles',
        security: [['bearerAuth' => []]],
        tags: ['Admin - Users'],
        parameters: [
            new OA\Parameter(name: 'user_id', in: 'path', required: true, schema: new OA\Schema(type: 'string')),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['roles'],
                properties: [
                    new OA\Property(property: 'roles', type: 'array', items: new OA\Items(type: 'string')),
                ],
            ),
        ),
        responses: [
            new OA\Response(response: 200, description: 'Roles updated', content: new OA\JsonContent(ref: '#/components/schemas/UserResponse')),
        ],
    )]
    public function updateRoles(ActorContext $actor, Request $request, string $userId): ApiResponse
    {
        $roles = $request->body['roles'] ?? [];
        $data = $this->updateRolesService->update($actor, $userId, is_array($roles) ? array_values($roles) : []);

        return ApiResponse::ok(['data' => $data, 'meta' => ['request_id' => uniqid('req_', true)]]);
    }

    #[OA\Post(
        path: '/admin/users/{user_id}/reset-password',
        summary: 'Reset user password',
        security: [['bearerAuth' => []]],
        tags: ['Admin - Users'],
        parameters: [
            new OA\Parameter(name: 'user_id', in: 'path', required: true, schema: new OA\Schema(type: 'string')),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['password'],
                properties: [
                    new OA\Property(property: 'password', type: 'string'),
                ],
            ),
        ),
        responses: [
            new OA\Response(response: 200, description: 'Password reset', content: new OA\JsonContent(ref: '#/components/schemas/UserResponse')),
        ],
    )]
    public function resetPassword(ActorContext $actor, Request $request, string $userId): ApiResponse
    {
        $password = (string) ($request->body['password'] ?? '');
        $data = $this->resetPasswordService->reset($actor, $userId, $password);

        return ApiResponse::ok(['data' => $data, 'meta' => ['request_id' => uniqid('req_', true)]]);
    }
}

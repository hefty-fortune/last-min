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

    public function list(ActorContext $actor, Request $request): ApiResponse
    {
        $providerId = isset($request->attributes['query']['provider_id'])
            ? (string) $request->attributes['query']['provider_id']
            : null;
        $data = $this->listService->list($actor, $providerId);

        return ApiResponse::ok(['data' => $data, 'meta' => ['request_id' => uniqid('req_', true)]]);
    }

    public function get(ActorContext $actor, string $userId): ApiResponse
    {
        $data = $this->getService->getById($actor, $userId);

        return ApiResponse::ok(['data' => $data, 'meta' => ['request_id' => uniqid('req_', true)]]);
    }

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

    public function updateRoles(ActorContext $actor, Request $request, string $userId): ApiResponse
    {
        $roles = $request->body['roles'] ?? [];
        $data = $this->updateRolesService->update($actor, $userId, is_array($roles) ? array_values($roles) : []);

        return ApiResponse::ok(['data' => $data, 'meta' => ['request_id' => uniqid('req_', true)]]);
    }

    public function resetPassword(ActorContext $actor, Request $request, string $userId): ApiResponse
    {
        $password = (string) ($request->body['password'] ?? '');
        $data = $this->resetPasswordService->reset($actor, $userId, $password);

        return ApiResponse::ok(['data' => $data, 'meta' => ['request_id' => uniqid('req_', true)]]);
    }
}

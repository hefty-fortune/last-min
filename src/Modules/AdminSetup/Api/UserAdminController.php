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

final class UserAdminController
{
    public function __construct(
        private CreateUserService $service,
        private GetUserService $getService,
        private ListUsersService $listService,
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
}

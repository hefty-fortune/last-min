<?php

declare(strict_types=1);

namespace App\Modules\AdminSetup\Api;

use App\Common\Api\ApiResponse;
use App\Common\Http\Request;
use App\Common\Security\ActorContext;
use App\Modules\AdminSetup\Application\Dto\CreateUserRequest;
use App\Modules\AdminSetup\Application\Service\CreateUserService;

final class UserAdminController
{
    public function __construct(private CreateUserService $service)
    {
    }

    public function create(ActorContext $actor, Request $request): ApiResponse
    {
        $roles = $request->body['roles'] ?? [];
        $data = $this->service->create($actor, new CreateUserRequest(
            firstName: (string) ($request->body['first_name'] ?? ''),
            lastName: (string) ($request->body['last_name'] ?? ''),
            email: (string) ($request->body['email'] ?? ''),
            phone: (string) ($request->body['phone'] ?? ''),
            roles: is_array($roles) ? array_values($roles) : [],
            providerId: (string) ($request->body['provider_id'] ?? ''),
        ));

        return ApiResponse::created(['data' => $data, 'meta' => ['request_id' => uniqid('req_', true)]]);
    }
}

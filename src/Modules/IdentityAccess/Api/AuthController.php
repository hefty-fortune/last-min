<?php

declare(strict_types=1);

namespace App\Modules\IdentityAccess\Api;

use App\Common\Api\ApiResponse;
use App\Common\Http\Request;
use App\Modules\IdentityAccess\Application\Service\LoginService;

final class AuthController
{
    public function __construct(private LoginService $loginService)
    {
    }

    public function login(Request $request): ApiResponse
    {
        $email = (string) ($request->body['email'] ?? '');
        $password = (string) ($request->body['password'] ?? '');

        $result = $this->loginService->login($email, $password);

        return ApiResponse::ok(['data' => $result, 'meta' => ['request_id' => uniqid('req_', true)]]);
    }

    public function logout(Request $request): ApiResponse
    {
        $authHeader = $request->header('Authorization');
        if (is_string($authHeader) && str_starts_with($authHeader, 'Bearer ')) {
            $token = trim(substr($authHeader, 7));
            $this->loginService->logout($token);
        }

        return ApiResponse::ok(['data' => ['logged_out' => true], 'meta' => ['request_id' => uniqid('req_', true)]]);
    }
}

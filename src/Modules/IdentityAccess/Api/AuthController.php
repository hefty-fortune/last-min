<?php

declare(strict_types=1);

namespace App\Modules\IdentityAccess\Api;

use App\Common\Api\ApiResponse;
use App\Common\Http\Request;
use App\Modules\IdentityAccess\Application\Service\LoginService;
use OpenApi\Attributes as OA;

final class AuthController
{
    public function __construct(private LoginService $loginService)
    {
    }

    #[OA\Post(
        path: '/auth/login',
        summary: 'Login with email and password',
        tags: ['Auth'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['email', 'password'],
                properties: [
                    new OA\Property(property: 'email', type: 'string', format: 'email'),
                    new OA\Property(property: 'password', type: 'string'),
                ],
            ),
        ),
        responses: [
            new OA\Response(response: 200, description: 'Login successful', content: new OA\JsonContent(ref: '#/components/schemas/LoginResponse')),
            new OA\Response(response: 401, description: 'Invalid credentials', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
        ],
    )]
    public function login(Request $request): ApiResponse
    {
        $email = (string) ($request->body['email'] ?? '');
        $password = (string) ($request->body['password'] ?? '');

        $result = $this->loginService->login($email, $password);

        return ApiResponse::ok(['data' => $result, 'meta' => ['request_id' => uniqid('req_', true)]]);
    }

    #[OA\Post(
        path: '/auth/logout',
        summary: 'Logout current session',
        security: [['bearerAuth' => []]],
        tags: ['Auth'],
        responses: [
            new OA\Response(response: 200, description: 'Logged out', content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'data', properties: [
                        new OA\Property(property: 'logged_out', type: 'boolean'),
                    ], type: 'object'),
                    new OA\Property(property: 'meta', ref: '#/components/schemas/Meta'),
                ],
            )),
        ],
    )]
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

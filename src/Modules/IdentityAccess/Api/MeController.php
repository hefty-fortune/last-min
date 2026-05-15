<?php

declare(strict_types=1);

namespace App\Modules\IdentityAccess\Api;

use App\Common\Api\ApiResponse;
use App\Common\Security\ActorContext;
use App\Modules\IdentityAccess\Application\Query\GetMeQueryService;
use OpenApi\Attributes as OA;

final class MeController
{
    public function __construct(private GetMeQueryService $queryService)
    {
    }

    #[OA\Get(
        path: '/me',
        summary: 'Get current authenticated user',
        security: [['bearerAuth' => []]],
        tags: ['Auth'],
        responses: [
            new OA\Response(response: 200, description: 'Current user info', content: new OA\JsonContent(ref: '#/components/schemas/MeResponse')),
            new OA\Response(response: 401, description: 'Unauthenticated', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
        ],
    )]
    public function get(ActorContext $actor): ApiResponse
    {
        return ApiResponse::ok(['data' => $this->queryService->get($actor), 'meta' => ['request_id' => uniqid('req_', true)]]);
    }
}

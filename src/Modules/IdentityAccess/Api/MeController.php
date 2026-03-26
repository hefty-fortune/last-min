<?php

declare(strict_types=1);

namespace App\Modules\IdentityAccess\Api;

use App\Common\Api\ApiResponse;
use App\Common\Security\ActorContext;
use App\Modules\IdentityAccess\Application\Query\GetMeQueryService;

final class MeController
{
    public function __construct(private GetMeQueryService $queryService)
    {
    }

    public function get(ActorContext $actor): ApiResponse
    {
        return ApiResponse::ok(['data' => $this->queryService->get($actor), 'meta' => ['request_id' => uniqid('req_', true)]]);
    }
}

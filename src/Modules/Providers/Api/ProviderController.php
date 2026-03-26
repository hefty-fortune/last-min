<?php

declare(strict_types=1);

namespace App\Modules\Providers\Api;

use App\Common\Api\ApiResponse;
use App\Common\Http\Request;
use App\Common\Security\ActorContext;
use App\Modules\Providers\Application\Dto\CreateProviderRequest;
use App\Modules\Providers\Application\Service\CreateProviderService;
use App\Platform\Idempotency\IdempotencyExecutor;

final class ProviderController
{
    public function __construct(private CreateProviderService $service, private IdempotencyExecutor $idempotency)
    {
    }

    public function create(ActorContext $actor, Request $request): ApiResponse
    {
        $idempotencyKey = $request->header('Idempotency-Key') ?? '';
        $result = $this->idempotency->execute('provider.create', $idempotencyKey, $request->body, function () use ($actor, $request): array {
            $data = $this->service->create($actor, new CreateProviderRequest((string) ($request->body['provider_type'] ?? '')));
            return [
                'status' => 201,
                'body' => ['data' => $data, 'meta' => ['request_id' => uniqid('req_', true), 'idempotency_replayed' => false]],
                'resource_type' => 'provider',
                'resource_id' => $data['provider_id'],
            ];
        });

        return new ApiResponse($result['status'], $result['body']);
    }
}

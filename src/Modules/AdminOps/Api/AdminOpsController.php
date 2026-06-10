<?php

declare(strict_types=1);

namespace App\Modules\AdminOps\Api;

use App\Common\Api\ApiResponse;
use App\Common\Http\Request;
use App\Common\Security\ActorContext;
use App\Modules\AdminOps\Application\Service\AdminOpsQueryService;
use App\Modules\AdminOps\Application\Service\ForceExpireOpeningService;
use App\Platform\Idempotency\IdempotencyExecutor;
use OpenApi\Attributes as OA;

final class AdminOpsController
{
    public function __construct(
        private AdminOpsQueryService $queries,
        private ForceExpireOpeningService $forceExpireService,
        private IdempotencyExecutor $idempotency,
    ) {
    }

    #[OA\Get(
        path: '/admin/bookings',
        summary: 'Operational booking list with payment summary',
        security: [['apiKey' => []]],
        tags: ['AdminOps'],
        parameters: [
            new OA\Parameter(name: 'state', in: 'query', required: false, schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'provider_id', in: 'query', required: false, schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'client_user_profile_id', in: 'query', required: false, schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'created_after', in: 'query', required: false, schema: new OA\Schema(type: 'string', format: 'date-time')),
            new OA\Parameter(name: 'created_before', in: 'query', required: false, schema: new OA\Schema(type: 'string', format: 'date-time')),
            new OA\Parameter(name: 'limit', in: 'query', required: false, schema: new OA\Schema(type: 'integer', default: 20)),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Bookings list projection'),
            new OA\Response(response: 403, description: 'Forbidden', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
        ],
    )]
    public function listBookings(ActorContext $actor, Request $request): ApiResponse
    {
        return $this->listResponse($this->queries->listBookings($actor, $this->query($request), $this->limit($request)));
    }

    #[OA\Get(
        path: '/admin/payments',
        summary: 'Operational payment list',
        security: [['apiKey' => []]],
        tags: ['AdminOps'],
        parameters: [
            new OA\Parameter(name: 'state', in: 'query', required: false, schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'provider_id', in: 'query', required: false, schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'limit', in: 'query', required: false, schema: new OA\Schema(type: 'integer', default: 20)),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Payments list projection'),
            new OA\Response(response: 403, description: 'Forbidden', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
        ],
    )]
    public function listPayments(ActorContext $actor, Request $request): ApiResponse
    {
        return $this->listResponse($this->queries->listPayments($actor, $this->query($request), $this->limit($request)));
    }

    #[OA\Get(
        path: '/admin/refunds',
        summary: 'Operational refund list',
        security: [['apiKey' => []]],
        tags: ['AdminOps'],
        parameters: [
            new OA\Parameter(name: 'state', in: 'query', required: false, schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'reason', in: 'query', required: false, schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'limit', in: 'query', required: false, schema: new OA\Schema(type: 'integer', default: 20)),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Refunds list projection'),
            new OA\Response(response: 403, description: 'Forbidden', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
        ],
    )]
    public function listRefunds(ActorContext $actor, Request $request): ApiResponse
    {
        return $this->listResponse($this->queries->listRefunds($actor, $this->query($request), $this->limit($request)));
    }

    #[OA\Get(
        path: '/admin/webhooks/stripe/events',
        summary: 'Operational visibility for ingested Stripe webhook events',
        security: [['apiKey' => []]],
        tags: ['AdminOps'],
        parameters: [
            new OA\Parameter(name: 'event_type', in: 'query', required: false, schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'status', in: 'query', required: false, schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'received_after', in: 'query', required: false, schema: new OA\Schema(type: 'string', format: 'date-time')),
            new OA\Parameter(name: 'received_before', in: 'query', required: false, schema: new OA\Schema(type: 'string', format: 'date-time')),
            new OA\Parameter(name: 'limit', in: 'query', required: false, schema: new OA\Schema(type: 'integer', default: 20)),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Webhook event read model'),
            new OA\Response(response: 403, description: 'Forbidden', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
        ],
    )]
    public function listStripeWebhookEvents(ActorContext $actor, Request $request): ApiResponse
    {
        return $this->listResponse($this->queries->listStripeWebhookEvents($actor, $this->query($request), $this->limit($request)));
    }

    #[OA\Post(
        path: '/admin/openings/{opening_id}:force-expire',
        summary: 'Force-expire an opening (super-admin override)',
        security: [['apiKey' => []]],
        tags: ['AdminOps'],
        parameters: [
            new OA\Parameter(name: 'opening_id', in: 'path', required: true, schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'Idempotency-Key', in: 'header', required: true, schema: new OA\Schema(type: 'string')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Opening force-expired'),
            new OA\Response(response: 403, description: 'Forbidden', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
            new OA\Response(response: 409, description: 'Invalid opening state', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
        ],
    )]
    public function forceExpireOpening(ActorContext $actor, Request $request, string $openingId): ApiResponse
    {
        $key = $request->header('Idempotency-Key') ?? '';
        $payload = $request->body + ['opening_id' => $openingId];
        $result = $this->idempotency->execute('admin.opening.force-expire', $key, $payload, function () use ($actor, $openingId): array {
            $data = $this->forceExpireService->forceExpire($actor, $openingId);

            return [
                'status' => 200,
                'body' => ['data' => $data, 'meta' => ['request_id' => uniqid('req_', true), 'idempotency_replayed' => false]],
                'resource_type' => 'opening',
                'resource_id' => $data['opening_id'],
            ];
        });

        return new ApiResponse($result['status'], $result['body']);
    }

    /** @return array<string, mixed> */
    private function query(Request $request): array
    {
        return (array) ($request->attributes['query'] ?? []);
    }

    private function limit(Request $request): int
    {
        return isset($request->attributes['query']['limit']) ? (int) $request->attributes['query']['limit'] : 20;
    }

    /** @param list<array<string, mixed>> $data */
    private function listResponse(array $data): ApiResponse
    {
        return ApiResponse::ok(['data' => $data, 'meta' => ['request_id' => uniqid('req_', true)]]);
    }
}

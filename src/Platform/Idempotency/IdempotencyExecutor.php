<?php

declare(strict_types=1);

namespace App\Platform\Idempotency;

use App\Common\Api\ApiError;
use App\Common\Api\ApiException;

final class IdempotencyExecutor
{
    public function __construct(private IdempotencyStore $store)
    {
    }

    public function execute(string $scope, string $key, array $payload, callable $callback): array
    {
        if (trim($key) === '') {
            throw new ApiException(422, new ApiError('VALIDATION_IDEMPOTENCY_KEY_REQUIRED', 'Idempotency-Key header is required.'));
        }

        $hash = hash('sha256', json_encode($payload, JSON_THROW_ON_ERROR));
        $existing = $this->store->find($scope, $key);

        if ($existing !== null) {
            if ($existing['request_hash'] !== $hash) {
                throw new ApiException(409, new ApiError('CONFLICT_IDEMPOTENCY_PAYLOAD_MISMATCH', 'Payload does not match original idempotent request.'));
            }

            if (!empty($existing['response_body'])) {
                $body = json_decode((string) $existing['response_body'], true, 512, JSON_THROW_ON_ERROR);
                $body['meta']['idempotency_replayed'] = true;
                return ['status' => (int) $existing['response_code'], 'body' => $body, 'replayed' => true];
            }
        } else {
            $this->store->reserve($scope, $key, $hash, new \DateTimeImmutable('+24 hours'));
        }

        $result = $callback();
        $this->store->storeResponse($scope, $key, $result['status'], $result['body'], $result['resource_type'] ?? null, $result['resource_id'] ?? null);

        return $result;
    }
}

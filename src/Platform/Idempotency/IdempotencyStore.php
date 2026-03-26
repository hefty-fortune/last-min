<?php

declare(strict_types=1);

namespace App\Platform\Idempotency;

interface IdempotencyStore
{
    public function find(string $scope, string $key): ?array;

    public function reserve(string $scope, string $key, string $requestHash, \DateTimeImmutable $expiresAt): void;

    public function storeResponse(string $scope, string $key, int $responseCode, array $responseBody, ?string $resourceType = null, ?string $resourceId = null): void;
}

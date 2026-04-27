<?php

declare(strict_types=1);

namespace App\Common\Security;

use App\Common\Api\ApiError;
use App\Common\Api\ApiException;
use App\Modules\IdentityAccess\Application\Port\ApiKeyRepository;

final class ApiKeyGateMiddleware
{
    public function __construct(private ApiKeyRepository $apiKeys)
    {
    }

    public function validate(array $headers): void
    {
        $apiKey = $headers['X-Api-Key'] ?? $headers['x-api-key'] ?? null;

        if (!is_string($apiKey) || trim($apiKey) === '') {
            throw new ApiException(401, new ApiError('AUTH_API_KEY_MISSING', 'X-Api-Key header is required.'));
        }

        $key = $this->apiKeys->findActiveByTokenHash(hash('sha256', $apiKey));
        if ($key === null) {
            throw new ApiException(401, new ApiError('AUTH_API_KEY_INVALID', 'Invalid API key.'));
        }
    }
}

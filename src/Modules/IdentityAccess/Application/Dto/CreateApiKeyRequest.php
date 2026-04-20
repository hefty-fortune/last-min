<?php

declare(strict_types=1);

namespace App\Modules\IdentityAccess\Application\Dto;

final readonly class CreateApiKeyRequest
{
    public function __construct(
        public string $clientId,
        public string $name,
    ) {
    }
}

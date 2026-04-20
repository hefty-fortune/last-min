<?php

declare(strict_types=1);

namespace App\Modules\AdminSetup\Application\Dto;

final readonly class CreateProviderRequest
{
    public function __construct(
        public string $organizationId,
        public string $displayName,
        public string $status,
    ) {
    }
}

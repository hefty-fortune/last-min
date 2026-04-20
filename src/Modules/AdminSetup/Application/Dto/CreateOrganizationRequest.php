<?php

declare(strict_types=1);

namespace App\Modules\AdminSetup\Application\Dto;

final readonly class CreateOrganizationRequest
{
    public function __construct(
        public string $legalName,
        public string $displayName,
        public ?string $taxId,
        public string $contactEmail,
        public string $contactPhone,
    ) {
    }
}

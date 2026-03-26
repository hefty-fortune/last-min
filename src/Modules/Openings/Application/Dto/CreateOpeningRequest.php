<?php

declare(strict_types=1);

namespace App\Modules\Openings\Application\Dto;

final readonly class CreateOpeningRequest
{
    public function __construct(
        public string $providerId,
        public string $serviceOfferingId,
        public string $startsAt,
        public string $endsAt,
        public array $priceOverride,
    ) {
    }
}

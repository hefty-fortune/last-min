<?php

declare(strict_types=1);

namespace App\Modules\Providers\Application\Dto;

final readonly class CreateProviderRequest
{
    public function __construct(public string $providerType)
    {
    }
}

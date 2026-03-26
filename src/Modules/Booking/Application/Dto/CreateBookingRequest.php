<?php

declare(strict_types=1);

namespace App\Modules\Booking\Application\Dto;

final readonly class CreateBookingRequest
{
    public function __construct(public string $openingId, public ?string $clientNote)
    {
    }
}

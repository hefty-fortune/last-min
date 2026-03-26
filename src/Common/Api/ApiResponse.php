<?php

declare(strict_types=1);

namespace App\Common\Api;

final readonly class ApiResponse
{
    public function __construct(public int $statusCode, public array $body)
    {
    }

    public static function ok(array $body): self
    {
        return new self(200, $body);
    }

    public static function created(array $body): self
    {
        return new self(201, $body);
    }
}

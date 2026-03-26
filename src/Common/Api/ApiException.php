<?php

declare(strict_types=1);

namespace App\Common\Api;

use RuntimeException;

final class ApiException extends RuntimeException
{
    public function __construct(public readonly int $statusCode, public readonly ApiError $error)
    {
        parent::__construct($error->message, $statusCode);
    }
}

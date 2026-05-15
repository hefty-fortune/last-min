<?php

declare(strict_types=1);

namespace App\Bootstrap;

use OpenApi\Attributes as OA;

#[OA\Info(
    version: '1.0.0',
    title: 'LastMin API',
    description: 'API documentation for the LastMin platform.',
)]
#[OA\Server(url: '/api/v1', description: 'API v1')]
#[OA\SecurityScheme(
    securityScheme: 'bearerAuth',
    type: 'http',
    scheme: 'bearer',
    bearerFormat: 'token',
)]
final class OpenApiInfo
{
}

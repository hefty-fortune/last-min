<?php

declare(strict_types=1);

namespace App\Bootstrap\Routing;

use App\Common\Api\ApiError;
use App\Common\Api\ApiException;
use App\Common\Api\ApiResponse;
use App\Common\Http\Request;

final class Router
{
    /** @var array<int, array{method:string, pattern:string, handler:callable}> */
    private array $routes = [];

    public function add(string $method, string $pattern, callable $handler): void
    {
        $this->routes[] = ['method' => strtoupper($method), 'pattern' => $pattern, 'handler' => $handler];
    }

    public function dispatch(Request $request): ApiResponse
    {
        foreach ($this->routes as $route) {
            if ($route['method'] !== strtoupper($request->method)) {
                continue;
            }
            $regex = '#^' . preg_replace('#\{([a-z_]+)\}#', '(?P<$1>[^/]+)', $route['pattern']) . '$#';
            if (preg_match($regex, $request->path, $matches) === 1) {
                $params = array_filter($matches, static fn ($k): bool => !is_int($k), ARRAY_FILTER_USE_KEY);

                try {
                    return $route['handler']($request, $params);
                } catch (ApiException $e) {
                    return new ApiResponse($e->statusCode, $e->error->toArray());
                }
            }
        }

        return new ApiResponse(404, (new ApiError('ROUTE_NOT_FOUND', 'Route not found.'))->toArray());
    }
}

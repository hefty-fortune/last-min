<?php

declare(strict_types=1);

namespace App\Common\Http;

final readonly class Request
{
    public function __construct(
        public string $method,
        public string $path,
        public array $headers,
        public array $body,
        public array $attributes = [],
        public string $rawBody = '',
    ) {
    }

    public function header(string $name): ?string
    {
        $needle = strtolower($name);
        foreach ($this->headers as $key => $value) {
            if (strtolower((string) $key) === $needle) {
                return (string) $value;
            }
        }

        return null;
    }
}

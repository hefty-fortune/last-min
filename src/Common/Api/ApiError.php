<?php

declare(strict_types=1);

namespace App\Common\Api;

final readonly class ApiError
{
    /** @param list<array{field?: string, issue?: string}> $details */
    public function __construct(
        public string $code,
        public string $message,
        public array $details = [],
        public bool $retryable = false,
        public ?string $requestId = null,
        public ?string $idempotencyKey = null,
    ) {
    }

    public function toArray(): array
    {
        return [
            'error' => [
                'code' => $this->code,
                'message' => $this->message,
                'details' => $this->details,
                'retryable' => $this->retryable,
                'request_id' => $this->requestId,
                'idempotency_key' => $this->idempotencyKey,
            ],
        ];
    }
}

<?php

declare(strict_types=1);

namespace App\Platform\Outbox;

interface OutboxMessageStore
{
    /** @param array<string, mixed> $payload */
    public function enqueue(string $messageType, array $payload, ?\DateTimeImmutable $availableAt = null): string;

    /** @return list<array<string, mixed>> */
    public function claimPending(int $limit): array;

    public function markDispatched(string $messageId): void;

    public function markFailed(string $messageId, string $error): void;

    public function recordAttemptFailure(string $messageId, string $error): void;
}

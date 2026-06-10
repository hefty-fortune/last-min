<?php

declare(strict_types=1);

namespace App\Platform\Outbox;

/**
 * Single-pass outbox drain for async job processing. Handlers are
 * registered per message type; a handler exception increments the
 * attempt counter and the message fails permanently after maxAttempts.
 * Intended to be invoked repeatedly by a worker loop or scheduler
 * (see bin/process-outbox.php).
 */
final class OutboxProcessor
{
    /** @var array<string, callable(array<string, mixed>): void> */
    private array $handlers = [];

    public function __construct(
        private OutboxMessageStore $store,
        private int $maxAttempts = 3,
    ) {
    }

    /** @param callable(array<string, mixed>): void $handler */
    public function register(string $messageType, callable $handler): void
    {
        $this->handlers[$messageType] = $handler;
    }

    /** @return array{dispatched: int, failed: int, skipped: int} */
    public function processPending(int $batchSize = 25): array
    {
        $dispatched = 0;
        $failed = 0;
        $skipped = 0;

        foreach ($this->store->claimPending($batchSize) as $message) {
            $handler = $this->handlers[$message['message_type']] ?? null;
            if ($handler === null) {
                $this->store->markFailed((string) $message['message_id'], 'No handler registered for message type.');
                $failed++;
                continue;
            }

            try {
                $handler($message['payload']);
                $this->store->markDispatched((string) $message['message_id']);
                $dispatched++;
            } catch (\Throwable $e) {
                if ($message['attempts'] + 1 >= $this->maxAttempts) {
                    $this->store->markFailed((string) $message['message_id'], $e->getMessage());
                    $failed++;
                } else {
                    $this->store->recordAttemptFailure((string) $message['message_id'], $e->getMessage());
                    $skipped++;
                }
            }
        }

        return ['dispatched' => $dispatched, 'failed' => $failed, 'skipped' => $skipped];
    }
}

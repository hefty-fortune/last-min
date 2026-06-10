<?php

declare(strict_types=1);

namespace App\Platform\Outbox;

use PDO;

final class PdoOutboxMessageStore implements OutboxMessageStore
{
    public function __construct(private PDO $pdo)
    {
    }

    public function enqueue(string $messageType, array $payload, ?\DateTimeImmutable $availableAt = null): string
    {
        $id = self::uuid();
        $now = new \DateTimeImmutable();
        $stmt = $this->pdo->prepare('INSERT INTO outbox_messages (id, message_type, payload, status, available_at, attempts, created_at) VALUES (:id, :message_type, :payload, :status, :available_at, 0, :created_at)');
        $stmt->execute([
            'id' => $id,
            'message_type' => $messageType,
            'payload' => json_encode($payload, JSON_THROW_ON_ERROR),
            'status' => 'pending',
            'available_at' => ($availableAt ?? $now)->format(DATE_ATOM),
            'created_at' => $now->format(DATE_ATOM),
        ]);

        return $id;
    }

    public function claimPending(int $limit): array
    {
        $stmt = $this->pdo->prepare("SELECT * FROM outbox_messages WHERE status = 'pending' AND available_at <= :now ORDER BY available_at ASC LIMIT :limit");
        $stmt->bindValue(':now', (new \DateTimeImmutable())->format(DATE_ATOM));
        $stmt->bindValue(':limit', max(1, min($limit, 100)), PDO::PARAM_INT);
        $stmt->execute();

        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return array_map(static fn (array $row): array => [
            'message_id' => $row['id'],
            'message_type' => $row['message_type'],
            'payload' => json_decode((string) $row['payload'], true, 512, JSON_THROW_ON_ERROR),
            'attempts' => (int) $row['attempts'],
        ], $rows);
    }

    public function markDispatched(string $messageId): void
    {
        $stmt = $this->pdo->prepare("UPDATE outbox_messages SET status = 'dispatched', dispatched_at = :now WHERE id = :id");
        $stmt->execute(['id' => $messageId, 'now' => (new \DateTimeImmutable())->format(DATE_ATOM)]);
    }

    public function markFailed(string $messageId, string $error): void
    {
        $stmt = $this->pdo->prepare("UPDATE outbox_messages SET status = 'failed', attempts = attempts + 1, last_error = :error WHERE id = :id");
        $stmt->execute(['id' => $messageId, 'error' => $error]);
    }

    public function recordAttemptFailure(string $messageId, string $error): void
    {
        $stmt = $this->pdo->prepare('UPDATE outbox_messages SET attempts = attempts + 1, last_error = :error WHERE id = :id');
        $stmt->execute(['id' => $messageId, 'error' => $error]);
    }

    private static function uuid(): string
    {
        $data = random_bytes(16);
        $data[6] = chr((ord($data[6]) & 0x0f) | 0x40);
        $data[8] = chr((ord($data[8]) & 0x3f) | 0x80);
        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
    }
}

<?php

declare(strict_types=1);

namespace App\Platform\Webhooks\Stripe;

use PDO;

final class PdoStripeWebhookEventRepository implements StripeWebhookEventRepository
{
    public function __construct(private PDO $pdo)
    {
    }

    public function findByStripeEventId(string $eventId): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM stripe_webhook_events WHERE stripe_event_id = :event_id LIMIT 1');
        $stmt->execute(['event_id' => $eventId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row === false ? null : $row;
    }

    public function insertReceived(string $eventId, string $eventType, array $payload, bool $signatureValid): void
    {
        $now = (new \DateTimeImmutable())->format(DATE_ATOM);
        $stmt = $this->pdo->prepare('INSERT INTO stripe_webhook_events (id, stripe_event_id, event_type, payload, signature_valid, processing_state, first_received_at, last_received_at, processed_at, failure_reason) VALUES (:id, :event_id, :event_type, :payload, :signature_valid, :state, :first_received, :last_received, NULL, NULL)');
        $stmt->execute([
            'id' => self::uuid(),
            'event_id' => $eventId,
            'event_type' => $eventType,
            'payload' => json_encode($payload, JSON_THROW_ON_ERROR),
            'signature_valid' => $signatureValid ? 1 : 0,
            'state' => 'received',
            'first_received' => $now,
            'last_received' => $now,
        ]);
    }

    public function markProcessed(string $eventId): void
    {
        $stmt = $this->pdo->prepare('UPDATE stripe_webhook_events SET processing_state = :state, processed_at = :processed_at, last_received_at = :last_received_at, failure_reason = NULL WHERE stripe_event_id = :event_id');
        $now = (new \DateTimeImmutable())->format(DATE_ATOM);
        $stmt->execute(['state' => 'processed', 'processed_at' => $now, 'last_received_at' => $now, 'event_id' => $eventId]);
    }

    public function markFailed(string $eventId, string $reason): void
    {
        $stmt = $this->pdo->prepare('UPDATE stripe_webhook_events SET processing_state = :state, failure_reason = :reason, last_received_at = :last_received_at WHERE stripe_event_id = :event_id');
        $stmt->execute(['state' => 'failed', 'reason' => $reason, 'last_received_at' => (new \DateTimeImmutable())->format(DATE_ATOM), 'event_id' => $eventId]);
    }

    private static function uuid(): string
    {
        $data = random_bytes(16);
        $data[6] = chr((ord($data[6]) & 0x0f) | 0x40);
        $data[8] = chr((ord($data[8]) & 0x3f) | 0x80);
        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
    }
}

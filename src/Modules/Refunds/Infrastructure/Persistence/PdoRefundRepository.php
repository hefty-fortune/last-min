<?php

declare(strict_types=1);

namespace App\Modules\Refunds\Infrastructure\Persistence;

use App\Modules\Refunds\Application\Port\RefundRepository;
use PDO;

final class PdoRefundRepository implements RefundRepository
{
    public function __construct(private PDO $pdo)
    {
    }

    public function findById(string $refundId): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM refunds WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $refundId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row === false ? null : $this->mapRefund($row);
    }

    public function lockById(string $refundId): ?array
    {
        $sql = 'SELECT * FROM refunds WHERE id = :id';
        if ($this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME) !== 'sqlite') {
            $sql .= ' FOR UPDATE';
        }
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(['id' => $refundId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row === false ? null : $this->mapRefund($row);
    }

    public function listByBookingId(string $bookingId): array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM refunds WHERE booking_id = :booking_id ORDER BY created_at DESC');
        $stmt->execute(['booking_id' => $bookingId]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return array_map(fn (array $row): array => $this->mapRefund($row), $rows);
    }

    public function hasActiveRefundForPayment(string $paymentId): bool
    {
        $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM refunds WHERE payment_id = :payment_id AND state IN ('requested', 'pending')");
        $stmt->execute(['payment_id' => $paymentId]);

        return (int) $stmt->fetchColumn() > 0;
    }

    public function createRequested(array $refund): array
    {
        $id = self::uuid();
        $now = (new \DateTimeImmutable())->format(DATE_ATOM);
        $stmt = $this->pdo->prepare('INSERT INTO refunds (id, payment_id, booking_id, state, reason, amount, currency, created_at, updated_at) VALUES (:id, :payment_id, :booking_id, :state, :reason, :amount, :currency, :created_at, :updated_at)');
        $stmt->execute([
            'id' => $id,
            'payment_id' => $refund['payment_id'],
            'booking_id' => $refund['booking_id'],
            'state' => 'requested',
            'reason' => $refund['reason'],
            'amount' => $refund['amount'],
            'currency' => $refund['currency'],
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $created = $this->findById($id);
        assert($created !== null);

        return $created;
    }

    public function recordDecision(string $refundId, string $state, string $decidedByActorId, ?string $note): array
    {
        $now = (new \DateTimeImmutable())->format(DATE_ATOM);
        $stmt = $this->pdo->prepare('UPDATE refunds SET state = :state, decided_by_actor_id = :decided_by, decision_note = :note, decided_at = :decided_at, updated_at = :updated_at WHERE id = :id');
        $stmt->execute([
            'id' => $refundId,
            'state' => $state,
            'decided_by' => $decidedByActorId,
            'note' => $note,
            'decided_at' => $now,
            'updated_at' => $now,
        ]);

        $updated = $this->findById($refundId);
        assert($updated !== null);

        return $updated;
    }

    public function listPendingIds(int $limit): array
    {
        $stmt = $this->pdo->prepare("SELECT id FROM refunds WHERE state = 'pending' ORDER BY created_at ASC LIMIT :limit");
        $stmt->bindValue(':limit', max(1, min($limit, 100)), PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    }

    public function markSucceeded(string $refundId, string $stripeRefundId): array
    {
        $stmt = $this->pdo->prepare("UPDATE refunds SET state = 'succeeded', stripe_refund_id = :stripe_refund_id, updated_at = :updated_at WHERE id = :id");
        $stmt->execute([
            'id' => $refundId,
            'stripe_refund_id' => $stripeRefundId,
            'updated_at' => (new \DateTimeImmutable())->format(DATE_ATOM),
        ]);

        $updated = $this->findById($refundId);
        assert($updated !== null);

        return $updated;
    }

    public function markFailed(string $refundId, string $reason): array
    {
        $stmt = $this->pdo->prepare("UPDATE refunds SET state = 'failed', failure_reason = :reason, updated_at = :updated_at WHERE id = :id");
        $stmt->execute([
            'id' => $refundId,
            'reason' => $reason,
            'updated_at' => (new \DateTimeImmutable())->format(DATE_ATOM),
        ]);

        $updated = $this->findById($refundId);
        assert($updated !== null);

        return $updated;
    }

    /** @param array<string, mixed> $row
     *  @return array<string, mixed>
     */
    private function mapRefund(array $row): array
    {
        return [
            'refund_id' => $row['id'],
            'payment_id' => $row['payment_id'],
            'booking_id' => $row['booking_id'],
            'state' => $row['state'],
            'reason' => $row['reason'],
            'amount' => ['currency' => $row['currency'], 'amount_minor' => (int) $row['amount']],
            'stripe_refund_id' => $row['stripe_refund_id'],
            'decided_by_actor_id' => $row['decided_by_actor_id'],
            'decision_note' => $row['decision_note'],
            'failure_reason' => $row['failure_reason'] ?? null,
            'decided_at' => $row['decided_at'],
            'created_at' => $row['created_at'],
            'updated_at' => $row['updated_at'],
        ];
    }

    private static function uuid(): string
    {
        $data = random_bytes(16);
        $data[6] = chr((ord($data[6]) & 0x0f) | 0x40);
        $data[8] = chr((ord($data[8]) & 0x3f) | 0x80);
        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
    }
}

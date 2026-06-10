<?php

declare(strict_types=1);

namespace App\Modules\AdminOps\Infrastructure\Persistence;

use App\Modules\AdminOps\Application\Port\AdminOpsReadRepository;
use PDO;

final class PdoAdminOpsReadRepository implements AdminOpsReadRepository
{
    public function __construct(private PDO $pdo)
    {
    }

    public function listBookings(array $filters, int $limit): array
    {
        [$where, $params] = $this->buildConditions($filters, [
            'state' => 'b.state = :state',
            'provider_id' => 'b.provider_id = :provider_id',
            'client_user_profile_id' => 'b.client_user_profile_id = :client_user_profile_id',
            'created_after' => 'b.created_at >= :created_after',
            'created_before' => 'b.created_at < :created_before',
        ]);

        $sql = 'SELECT b.*, p.id AS payment_id, p.state AS payment_state FROM bookings b LEFT JOIN payments p ON p.booking_id = b.id'
            . $where . ' ORDER BY b.created_at DESC LIMIT :limit';

        $rows = $this->fetch($sql, $params, $limit);

        return array_map(static fn (array $row): array => [
            'booking_id' => $row['id'],
            'opening_id' => $row['opening_id'],
            'provider_id' => $row['provider_id'],
            'client_user_profile_id' => $row['client_user_profile_id'],
            'state' => $row['state'],
            'amount' => ['currency' => $row['payment_currency'], 'amount_minor' => (int) $row['payment_required_amount']],
            'payment' => $row['payment_id'] === null ? null : ['payment_id' => $row['payment_id'], 'state' => $row['payment_state']],
            'no_show_actor' => $row['no_show_actor'],
            'created_at' => $row['created_at'],
            'updated_at' => $row['updated_at'],
        ], $rows);
    }

    public function listPayments(array $filters, int $limit): array
    {
        [$where, $params] = $this->buildConditions($filters, [
            'state' => 'state = :state',
            'provider_id' => 'provider_id = :provider_id',
            'created_after' => 'created_at >= :created_after',
            'created_before' => 'created_at < :created_before',
        ]);

        $rows = $this->fetch('SELECT * FROM payments' . $where . ' ORDER BY created_at DESC LIMIT :limit', $params, $limit);

        return array_map(static fn (array $row): array => [
            'payment_id' => $row['id'],
            'booking_id' => $row['booking_id'],
            'provider_id' => $row['provider_id'],
            'state' => $row['state'],
            'amount' => ['currency' => $row['currency'], 'amount_minor' => (int) $row['amount']],
            'stripe_payment_intent_id' => $row['stripe_payment_intent_id'],
            'failed_reason' => $row['failed_reason'],
            'created_at' => $row['created_at'],
            'updated_at' => $row['updated_at'],
        ], $rows);
    }

    public function listRefunds(array $filters, int $limit): array
    {
        [$where, $params] = $this->buildConditions($filters, [
            'state' => 'state = :state',
            'reason' => 'reason = :reason',
            'created_after' => 'created_at >= :created_after',
            'created_before' => 'created_at < :created_before',
        ]);

        $rows = $this->fetch('SELECT * FROM refunds' . $where . ' ORDER BY created_at DESC LIMIT :limit', $params, $limit);

        return array_map(static fn (array $row): array => [
            'refund_id' => $row['id'],
            'payment_id' => $row['payment_id'],
            'booking_id' => $row['booking_id'],
            'state' => $row['state'],
            'reason' => $row['reason'],
            'amount' => ['currency' => $row['currency'], 'amount_minor' => (int) $row['amount']],
            'decided_by_actor_id' => $row['decided_by_actor_id'],
            'decided_at' => $row['decided_at'],
            'created_at' => $row['created_at'],
        ], $rows);
    }

    public function listStripeWebhookEvents(array $filters, int $limit): array
    {
        [$where, $params] = $this->buildConditions($filters, [
            'event_type' => 'event_type = :event_type',
            'status' => 'processing_state = :status',
            'received_after' => 'last_received_at >= :received_after',
            'received_before' => 'last_received_at < :received_before',
        ]);

        $rows = $this->fetch('SELECT * FROM stripe_webhook_events' . $where . ' ORDER BY last_received_at DESC LIMIT :limit', $params, $limit);

        // Payload intentionally excluded from the list projection.
        return array_map(static fn (array $row): array => [
            'event_id' => $row['stripe_event_id'],
            'event_type' => $row['event_type'],
            'processing_status' => $row['processing_state'],
            'signature_valid' => (bool) $row['signature_valid'],
            'first_received_at' => $row['first_received_at'],
            'last_received_at' => $row['last_received_at'],
            'processed_at' => $row['processed_at'],
            'last_error' => $row['failure_reason'],
        ], $rows);
    }

    /** @param array<string, mixed> $filters
     *  @param array<string, string> $allowed
     *  @return array{0: string, 1: array<string, string>}
     */
    private function buildConditions(array $filters, array $allowed): array
    {
        $conditions = [];
        $params = [];
        foreach ($allowed as $key => $condition) {
            $value = $filters[$key] ?? null;
            if ($value !== null && trim((string) $value) !== '') {
                $conditions[] = $condition;
                $params[$key] = (string) $value;
            }
        }

        return [$conditions === [] ? '' : ' WHERE ' . implode(' AND ', $conditions), $params];
    }

    /** @param array<string, string> $params
     *  @return list<array<string, mixed>>
     */
    private function fetch(string $sql, array $params, int $limit): array
    {
        $stmt = $this->pdo->prepare($sql);
        foreach ($params as $key => $value) {
            $stmt->bindValue(':' . $key, $value);
        }
        $stmt->bindValue(':limit', max(1, min($limit, 100)), PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}

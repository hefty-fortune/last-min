<?php

declare(strict_types=1);

namespace App\Modules\Payments\Infrastructure\Persistence;

use App\Modules\Payments\Application\Port\PaymentRepository;
use PDO;

final class PdoPaymentRepository implements PaymentRepository
{
    public function __construct(private PDO $pdo)
    {
    }

    public function findByBookingId(string $bookingId): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM payments WHERE booking_id = :booking_id LIMIT 1');
        $stmt->execute(['booking_id' => $bookingId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row === false ? null : $row;
    }

    public function createInitiated(array $payment): array
    {
        $id = self::uuid();
        $now = (new \DateTimeImmutable())->format(DATE_ATOM);
        $stmt = $this->pdo->prepare('INSERT INTO payments (id, booking_id, provider_id, client_user_profile_id, state, amount, currency, stripe_payment_intent_id, created_at, updated_at) VALUES (:id, :booking_id, :provider_id, :client_id, :state, :amount, :currency, NULL, :created_at, :updated_at)');
        $stmt->execute([
            'id' => $id,
            'booking_id' => $payment['booking_id'],
            'provider_id' => $payment['provider_id'],
            'client_id' => $payment['client_user_profile_id'],
            'state' => 'initiated',
            'amount' => $payment['amount'],
            'currency' => $payment['currency'],
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        return ['payment_id' => $id, 'state' => 'initiated', 'amount' => ['currency' => $payment['currency'], 'amount_minor' => (int) $payment['amount']]];
    }

    public function attachStripeIntent(string $paymentId, string $intentId): void
    {
        $stmt = $this->pdo->prepare('UPDATE payments SET stripe_payment_intent_id = :intent_id, updated_at = :updated WHERE id = :id');
        $stmt->execute(['intent_id' => $intentId, 'updated' => (new \DateTimeImmutable())->format(DATE_ATOM), 'id' => $paymentId]);
    }

    private static function uuid(): string
    {
        $data = random_bytes(16);
        $data[6] = chr((ord($data[6]) & 0x0f) | 0x40);
        $data[8] = chr((ord($data[8]) & 0x3f) | 0x80);
        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
    }
}

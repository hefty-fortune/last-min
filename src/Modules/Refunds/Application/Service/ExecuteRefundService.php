<?php

declare(strict_types=1);

namespace App\Modules\Refunds\Application\Service;

use App\Modules\Payments\Application\Port\PaymentRepository;
use App\Modules\Refunds\Application\Port\RefundRepository;
use App\Platform\Audit\AuditLogger;
use App\Platform\Integrations\Stripe\StripeGateway;
use App\Platform\Persistence\TransactionManager;

/**
 * Executes approved (pending) refunds against the payment gateway.
 * Triggered by the refund.approved outbox event and additionally swept
 * by the worker so a lost event cannot strand a pending refund.
 */
final class ExecuteRefundService
{
    public function __construct(
        private TransactionManager $tx,
        private RefundRepository $refunds,
        private PaymentRepository $payments,
        private StripeGateway $gateway,
        private AuditLogger $audit,
    ) {
    }

    /** @return array{succeeded: int, failed: int} */
    public function executePending(int $batchSize = 25): array
    {
        $succeeded = 0;
        $failed = 0;

        foreach ($this->refunds->listPendingIds($batchSize) as $refundId) {
            $result = $this->executeOne((string) $refundId);
            if ($result === 'succeeded') {
                $succeeded++;
            } elseif ($result === 'failed') {
                $failed++;
            }
        }

        return ['succeeded' => $succeeded, 'failed' => $failed];
    }

    /** @return 'succeeded'|'failed'|'skipped' */
    public function executeOne(string $refundId): string
    {
        $refund = $this->refunds->findById($refundId);
        if ($refund === null || $refund['state'] !== 'pending') {
            return 'skipped';
        }

        $payment = $this->payments->findById((string) $refund['payment_id']);
        if ($payment === null) {
            $this->refunds->markFailed($refundId, 'Payment record not found.');
            return 'failed';
        }
        if (!in_array((string) $payment['state'], ['captured', 'succeeded'], true)) {
            $this->refunds->markFailed($refundId, sprintf('Payment is in state "%s"; only captured payments are refundable.', (string) $payment['state']));
            return 'failed';
        }
        $intentId = $payment['stripe_payment_intent_id'];
        if (!is_string($intentId) || trim($intentId) === '') {
            $this->refunds->markFailed($refundId, 'Payment has no gateway payment intent.');
            return 'failed';
        }

        try {
            // Gateway call happens OUTSIDE the transaction (network I/O);
            // the gateway-side idempotency key makes retries safe.
            $gatewayRefund = $this->gateway->createRefund($refundId, $intentId, (int) $refund['amount']['amount_minor']);
        } catch (\Throwable $e) {
            // Transient gateway failure: keep the refund pending so the next
            // worker pass retries it. Permanent failures surface via Stripe
            // rejecting the idempotent retry with the same error.
            $this->audit->record(null, 'refund.execution-retry', 'refund', $refundId, ['error' => $e->getMessage()]);
            return 'skipped';
        }

        $this->tx->withinTransaction(function () use ($refundId, $payment, $gatewayRefund): void {
            $this->refunds->markSucceeded($refundId, (string) $gatewayRefund['refund_id']);
            $this->payments->markRefunded((string) $payment['id']);
            $this->audit->record(null, 'refund.executed', 'refund', $refundId, [
                'stripe_refund_id' => $gatewayRefund['refund_id'],
                'payment_id' => $payment['id'],
            ]);
        });

        return 'succeeded';
    }
}

<?php

declare(strict_types=1);

namespace App\Modules\Refunds\Application\Service;

use App\Common\Api\ApiError;
use App\Common\Api\ApiException;
use App\Common\Security\ActorContext;
use App\Modules\Refunds\Application\Port\RefundRepository;
use App\Platform\Audit\AuditLogger;
use App\Platform\Outbox\OutboxMessageStore;
use App\Platform\Persistence\TransactionManager;

final class ApproveRefundService
{
    public function __construct(
        private TransactionManager $tx,
        private RefundRepository $refunds,
        private AuditLogger $audit,
        private OutboxMessageStore $outbox,
    ) {
    }

    /**
     * Manual exception workflow: admin review of a requested refund.
     * Approval moves requested -> pending; actual gateway execution is
     * driven asynchronously (extension point).
     *
     * @return array<string, mixed>
     */
    public function approve(ActorContext $actor, string $refundId, ?string $note): array
    {
        if (!$actor->hasRole('admin') && !$actor->hasRole('super-admin')) {
            throw new ApiException(403, new ApiError('FORBIDDEN_ROLE_MISSING', 'Admin role is required.'));
        }

        // Lock the refund row inside a transaction so concurrent approvals
        // serialize: the state guard and transition are now atomic, preventing
        // a double 'refund.approved' outbox event (and a downstream double refund).
        return $this->tx->withinTransaction(function () use ($actor, $refundId, $note): array {
            $refund = $this->refunds->lockById($refundId);
            if ($refund === null) {
                throw new ApiException(404, new ApiError('REFUND_NOT_FOUND', 'Refund was not found.'));
            }
            if ($refund['state'] !== 'requested') {
                throw new ApiException(409, new ApiError('REFUND_STATE_INVALID', 'Only requested refunds can be approved.'));
            }

            $approved = $this->refunds->recordDecision($refundId, 'pending', $actor->actorId, $note);

            $this->audit->record($actor, 'refund.approve', 'refund', $refundId, ['note' => $note]);
            $this->outbox->enqueue('refund.approved', [
                'refund_id' => $refundId,
                'payment_id' => $approved['payment_id'],
                'booking_id' => $approved['booking_id'],
            ]);

            return $approved;
        });
    }
}

#!/usr/bin/env php
<?php

declare(strict_types=1);

use App\Bootstrap\DatabaseConnection;
use App\Modules\Booking\Application\Service\ExpireReservationsService;
use App\Modules\Booking\Infrastructure\Persistence\PdoBookingRepository;
use App\Modules\Openings\Infrastructure\Persistence\PdoOpeningRepository;
use App\Modules\Payments\Infrastructure\Persistence\PdoPaymentRepository;
use App\Modules\Refunds\Application\Service\ExecuteRefundService;
use App\Modules\Refunds\Infrastructure\Persistence\PdoRefundRepository;
use App\Platform\Audit\PdoAuditLogger;
use App\Platform\Integrations\Stripe\HttpStripeGateway;
use App\Platform\Integrations\Stripe\StubStripeGateway;
use App\Platform\Outbox\OutboxProcessor;
use App\Platform\Outbox\PdoOutboxMessageStore;
use App\Platform\Persistence\PdoTransactionManager;

require __DIR__ . '/../vendor/autoload.php';

// Single worker pass: expire stale reservations, execute approved refunds,
// drain the outbox. Run periodically (the docker-compose `worker` service
// loops it every 30 seconds).

$pdo = DatabaseConnection::fromEnvironment();
$tx = new PdoTransactionManager($pdo);
$audit = new PdoAuditLogger($pdo);
$bookings = new PdoBookingRepository($pdo);
$openings = new PdoOpeningRepository($pdo);
$refunds = new PdoRefundRepository($pdo);
$payments = new PdoPaymentRepository($pdo);

$stripeMode = getenv('STRIPE_MODE') ?: 'simulation';
$gateway = $stripeMode === 'real'
    ? new HttpStripeGateway(getenv('STRIPE_SECRET_KEY') ?: '')
    : new StubStripeGateway();

$expiry = new ExpireReservationsService($tx, $bookings, $openings, $audit);
$refundExecution = new ExecuteRefundService($tx, $refunds, $payments, $gateway, $audit);

$expired = $expiry->expireDue();
$refunded = $refundExecution->executePending();

$processor = new OutboxProcessor(new PdoOutboxMessageStore($pdo));
$processor->register('refund.approved', static function (array $payload) use ($refundExecution): void {
    $refundExecution->executeOne((string) ($payload['refund_id'] ?? ''));
});
$processor->register('booking.provider_no_show', static function (array $payload): void {
    // Extension point: notify the client that the provider did not show.
});
$processor->register('booking.client_no_show', static function (array $payload): void {
    // Extension point: notify provider records / analytics.
});

$outbox = $processor->processPending();

echo sprintf(
    "Worker pass: %d reservations expired, %d refunds succeeded, %d refunds failed, outbox %d dispatched / %d failed / %d retried.\n",
    $expired['expired'],
    $refunded['succeeded'],
    $refunded['failed'],
    $outbox['dispatched'],
    $outbox['failed'],
    $outbox['skipped'],
);

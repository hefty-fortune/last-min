#!/usr/bin/env php
<?php

declare(strict_types=1);

use App\Bootstrap\DatabaseConnection;
use App\Platform\Outbox\OutboxProcessor;
use App\Platform\Outbox\PdoOutboxMessageStore;

require __DIR__ . '/../vendor/autoload.php';

$pdo = DatabaseConnection::fromEnvironment();
$processor = new OutboxProcessor(new PdoOutboxMessageStore($pdo));

// Handler registry. Notification/webhook-followup handlers plug in here
// as they are implemented; unhandled types fail visibly instead of
// silently disappearing.
$processor->register('booking.provider_no_show', static function (array $payload): void {
    // Extension point: notify client + trigger refund execution follow-up.
});
$processor->register('booking.client_no_show', static function (array $payload): void {
    // Extension point: notify provider records / analytics.
});
$processor->register('refund.approved', static function (array $payload): void {
    // Extension point: execute gateway refund via Stripe and update refund state.
});

$result = $processor->processPending();

echo sprintf(
    "Outbox pass complete: %d dispatched, %d failed, %d retried later.\n",
    $result['dispatched'],
    $result['failed'],
    $result['skipped'],
);

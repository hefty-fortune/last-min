<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Common\Api\ApiException;
use App\Common\Security\ActorContext;
use App\Modules\Booking\Infrastructure\Persistence\PdoBookingRepository;
use App\Modules\Openings\Infrastructure\Persistence\PdoOpeningRepository;
use App\Modules\Payments\Api\PaymentController;
use App\Modules\Payments\Application\Service\GetPaymentService;
use App\Modules\Payments\Application\Service\InitiatePaymentService;
use App\Modules\Payments\Application\Service\SettlePaymentOutcomeService;
use App\Modules\Payments\Infrastructure\Persistence\PdoPaymentRepository;
use App\Modules\Providers\Infrastructure\Persistence\PdoProviderRepository;
use App\Platform\Idempotency\IdempotencyExecutor;
use App\Platform\Idempotency\PdoIdempotencyStore;
use App\Platform\Integrations\Stripe\StubStripeGateway;
use App\Platform\Persistence\PdoTransactionManager;
use App\Platform\Webhooks\Stripe\StripeSignatureVerifier;
use PDO;
use PHPUnit\Framework\TestCase;

final class StripeRealModeTest extends TestCase
{
    public function testVerifierAcceptsRealStripeSignatureScheme(): void
    {
        $verifier = new StripeSignatureVerifier('whsec_test');
        $body = '{"id":"evt_1","type":"payment_intent.succeeded"}';
        $timestamp = time();
        $signature = sprintf('t=%d,v1=%s', $timestamp, hash_hmac('sha256', $timestamp . '.' . $body, 'whsec_test'));

        self::assertTrue($verifier->isValid($body, $signature));
    }

    public function testVerifierRejectsWrongSecretAndStaleTimestamp(): void
    {
        $verifier = new StripeSignatureVerifier('whsec_test');
        $body = '{"id":"evt_1"}';

        $timestamp = time();
        $wrongSecret = sprintf('t=%d,v1=%s', $timestamp, hash_hmac('sha256', $timestamp . '.' . $body, 'other-secret'));
        self::assertFalse($verifier->isValid($body, $wrongSecret));

        $stale = $timestamp - 3600;
        $staleSignature = sprintf('t=%d,v1=%s', $stale, hash_hmac('sha256', $stale . '.' . $body, 'whsec_test'));
        self::assertFalse($verifier->isValid($body, $staleSignature));
    }

    public function testVerifierStillAcceptsLegacyDevScheme(): void
    {
        $verifier = new StripeSignatureVerifier('dev-secret');
        $body = '{"id":"evt_2"}';

        self::assertTrue($verifier->isValid($body, hash_hmac('sha256', $body, 'dev-secret')));
        self::assertFalse($verifier->isValid($body, 'nonsense'));
    }

    public function testSimulationEndpointsDisabledInRealMode(): void
    {
        $pdo = new PDO('sqlite::memory:');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        foreach (glob(__DIR__ . '/../../migrations/*.sql') as $migration) {
            $pdo->exec((string) file_get_contents($migration));
        }

        $tx = new PdoTransactionManager($pdo);
        $controller = new PaymentController(
            new InitiatePaymentService(new PdoBookingRepository($pdo), new PdoPaymentRepository($pdo), new StubStripeGateway(), 'real'),
            new GetPaymentService(new PdoPaymentRepository($pdo), new PdoProviderRepository($pdo)),
            new SettlePaymentOutcomeService($tx, new PdoPaymentRepository($pdo), new PdoBookingRepository($pdo), new PdoOpeningRepository($pdo)),
            new PdoPaymentRepository($pdo),
            new IdempotencyExecutor(new PdoIdempotencyStore($pdo)),
            simulationEnabled: false,
        );

        $admin = new ActorContext('admin-1', 'sso|admin', ['admin'], null);

        try {
            $controller->simulateSucceed($admin, 'payment-x');
            self::fail('Expected SIMULATION_DISABLED exception.');
        } catch (ApiException $e) {
            self::assertSame(403, $e->statusCode);
            self::assertSame('SIMULATION_DISABLED', $e->error->code);
        }
    }
}

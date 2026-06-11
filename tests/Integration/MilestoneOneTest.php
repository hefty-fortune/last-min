<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Bootstrap\Routing\ApiV1Routes;
use App\Bootstrap\Routing\Router;
use App\Modules\AdminOps\Api\AdminOpsController;
use App\Modules\AdminOps\Application\Service\AdminOpsQueryService;
use App\Modules\AdminOps\Application\Service\ForceExpireOpeningService;
use App\Modules\AdminOps\Infrastructure\Persistence\PdoAdminOpsReadRepository;
use App\Common\Http\Request;
use App\Common\Security\ActorContextResolver;
use App\Modules\AdminSetup\Api\OrganizationAdminController;
use App\Modules\AdminSetup\Api\ProviderAdminController;
use App\Modules\AdminSetup\Api\UserAdminController;
use App\Modules\AdminSetup\Application\Service\CreateOrganizationService;
use App\Modules\AdminSetup\Application\Service\CreateProviderService as CreateAdminProviderService;
use App\Modules\AdminSetup\Application\Service\CreateUserService;
use App\Modules\AdminSetup\Application\Service\GetOrganizationService;
use App\Modules\AdminSetup\Application\Service\GetProviderService;
use App\Modules\AdminSetup\Application\Service\GetUserService;
use App\Modules\AdminSetup\Application\Service\ListOrganizationsService;
use App\Modules\AdminSetup\Application\Service\ListProvidersService;
use App\Modules\AdminSetup\Application\Service\ListUsersService;
use App\Modules\AdminSetup\Application\Service\ResetUserPasswordService;
use App\Modules\AdminSetup\Application\Service\UpdateUserRolesService;
use App\Modules\AdminSetup\Application\Service\UpdateUserService;
use App\Modules\AdminSetup\Infrastructure\Persistence\PdoAdminProviderRepository;
use App\Modules\AdminSetup\Infrastructure\Persistence\PdoOrganizationRepository;
use App\Modules\AdminSetup\Infrastructure\Persistence\PdoUserRepository;
use App\Modules\Booking\Api\BookingController;
use App\Modules\Booking\Application\Service\CreateBookingService;
use App\Modules\Booking\Application\Service\ExpireReservationsService;
use App\Modules\Booking\Application\Service\GetBookingService;
use App\Modules\Booking\Application\Service\ListMyBookingsService;
use App\Modules\Booking\Application\Service\MarkNoShowService;
use App\Modules\Booking\Infrastructure\Persistence\PdoBookingRepository;
use App\Modules\IdentityAccess\Api\ApiKeyController;
use App\Modules\IdentityAccess\Api\AuthController;
use App\Modules\IdentityAccess\Api\MeController;
use App\Modules\IdentityAccess\Application\Query\GetMeQueryService;
use App\Modules\IdentityAccess\Application\Service\CreateApiKeyService;
use App\Modules\IdentityAccess\Application\Service\DeleteApiKeyService;
use App\Modules\IdentityAccess\Application\Service\ListApiKeysService;
use App\Modules\IdentityAccess\Application\Service\LoginService;
use App\Modules\IdentityAccess\Infrastructure\Persistence\PdoApiKeyRepository;
use App\Modules\IdentityAccess\Infrastructure\Persistence\PdoAuthSessionRepository;
use App\Modules\IdentityAccess\Infrastructure\Persistence\PdoUserAuthRepository;
use App\Modules\IdentityAccess\Infrastructure\Security\ApiKeyBearerTokenActorResolver;
use App\Modules\Openings\Api\OpeningController;
use App\Modules\Openings\Application\Service\CancelOpeningService;
use App\Modules\Openings\Application\Service\CreateOpeningService;
use App\Modules\Openings\Application\Service\GetOpeningService;
use App\Modules\Openings\Application\Service\ListOpeningsService;
use App\Modules\Openings\Application\Service\OpeningAccessService;
use App\Modules\Openings\Application\Service\PublishOpeningService;
use App\Modules\Openings\Infrastructure\Persistence\PdoOpeningRepository;
use App\Modules\Organizations\Api\OrganizationController;
use App\Modules\Organizations\Application\Service\AddOrganizationMemberService;
use App\Modules\Organizations\Application\Service\CreateOrganizationSelfService;
use App\Modules\Organizations\Application\Service\ViewOrganizationService;
use App\Modules\Organizations\Infrastructure\Persistence\PdoOrganizationMemberRepository;
use App\Modules\Payments\Api\PaymentController;
use App\Modules\Payments\Application\Service\GetPaymentService;
use App\Modules\Payments\Application\Service\InitiatePaymentService;
use App\Modules\Payments\Application\Service\SettlePaymentOutcomeService;
use App\Modules\Payments\Infrastructure\Persistence\PdoPaymentRepository;
use App\Modules\Providers\Api\ProviderController;
use App\Modules\Providers\Application\Service\CreateProviderService;
use App\Modules\Providers\Application\Service\GetProviderProfileService;
use App\Modules\Providers\Application\Service\LinkProviderService;
use App\Modules\Providers\Application\Service\UpdateProviderService;
use App\Modules\Providers\Infrastructure\Persistence\PdoProviderRepository;
use App\Modules\Refunds\Api\RefundController;
use App\Modules\Refunds\Application\Service\ApproveRefundService;
use App\Modules\Refunds\Application\Service\ExecuteRefundService;
use App\Modules\Refunds\Application\Service\ListBookingRefundsService;
use App\Modules\Refunds\Application\Service\RequestRefundService;
use App\Modules\Refunds\Infrastructure\Persistence\PdoRefundRepository;
use App\Modules\ServiceCatalog\Api\OfferingController;
use App\Modules\ServiceCatalog\Application\Service\CreateOfferingService;
use App\Modules\ServiceCatalog\Application\Service\ListOfferingsService;
use App\Modules\ServiceCatalog\Application\Service\OfferingAccessService;
use App\Modules\ServiceCatalog\Application\Service\UpdateOfferingService;
use App\Modules\ServiceCatalog\Infrastructure\Persistence\PdoOfferingRepository;
use App\Platform\Audit\PdoAuditLogger;
use App\Platform\Idempotency\IdempotencyExecutor;
use App\Platform\Outbox\OutboxProcessor;
use App\Platform\Outbox\PdoOutboxMessageStore;
use App\Platform\Idempotency\PdoIdempotencyStore;
use App\Platform\Integrations\Stripe\StubStripeGateway;
use App\Platform\Persistence\PdoTransactionManager;
use App\Platform\Webhooks\Stripe\PdoStripeWebhookEventRepository;
use App\Platform\Webhooks\Stripe\StripeSignatureVerifier;
use App\Platform\Webhooks\Stripe\StripeWebhookController;
use App\Platform\Webhooks\Stripe\StripeWebhookDispatcher;
use PDO;
use PHPUnit\Framework\TestCase;

final class MilestoneOneTest extends TestCase
{
    private PDO $pdo;
    private Router $router;
    private PdoApiKeyRepository $apiKeys;

    protected function setUp(): void
    {
        $this->pdo = new PDO('sqlite::memory:');
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $migrations = glob(__DIR__ . '/../../migrations/*.sql');
        foreach ($migrations as $migration) {
            $sql = file_get_contents($migration);
            self::assertNotFalse($sql);
            $this->pdo->exec($sql);
        }

        $this->seedFixtureData();

        $idempotency = new IdempotencyExecutor(new PdoIdempotencyStore($this->pdo));
        $apiKeys = new PdoApiKeyRepository($this->pdo);
        $this->apiKeys = $apiKeys;
        $this->router = new Router();
        $providerRepository = new PdoProviderRepository($this->pdo);
        $openingRepository = new PdoOpeningRepository($this->pdo);
        $tx = new PdoTransactionManager($this->pdo);
        $openingAccess = new OpeningAccessService($providerRepository);

        (new ApiV1Routes(new ActorContextResolver(new ApiKeyBearerTokenActorResolver($apiKeys))))->register(
            $this->router,
            new OrganizationAdminController(
                new CreateOrganizationService(new PdoOrganizationRepository($this->pdo)),
                new GetOrganizationService(new PdoOrganizationRepository($this->pdo)),
                new ListOrganizationsService(new PdoOrganizationRepository($this->pdo))
            ),
            new ProviderAdminController(
                new CreateAdminProviderService(new PdoOrganizationRepository($this->pdo), new PdoAdminProviderRepository($this->pdo)),
                new GetProviderService(new PdoAdminProviderRepository($this->pdo)),
                new ListProvidersService(new PdoAdminProviderRepository($this->pdo))
            ),
            new UserAdminController(
                new CreateUserService(new PdoAdminProviderRepository($this->pdo), new PdoUserRepository($this->pdo)),
                new GetUserService(new PdoUserRepository($this->pdo)),
                new ListUsersService(new PdoUserRepository($this->pdo)),
                new UpdateUserService(new PdoUserRepository($this->pdo)),
                new UpdateUserRolesService(new PdoUserRepository($this->pdo)),
                new ResetUserPasswordService(new PdoUserRepository($this->pdo))
            ),
            new ApiKeyController(new CreateApiKeyService($apiKeys), new DeleteApiKeyService($apiKeys), new ListApiKeysService($apiKeys)),
            new AuthController(new LoginService(new PdoUserAuthRepository($this->pdo), new PdoAuthSessionRepository($this->pdo))),
            new MeController(new GetMeQueryService()),
            new ProviderController(
                new CreateProviderService($providerRepository),
                new GetProviderProfileService($providerRepository),
                new UpdateProviderService($providerRepository),
                new LinkProviderService($providerRepository),
                $idempotency
            ),
            new OpeningController(
                new CreateOpeningService($openingRepository, $openingAccess),
                new GetOpeningService($openingRepository, $openingAccess),
                new ListOpeningsService($openingRepository, $openingAccess),
                new PublishOpeningService($tx, $openingRepository, $openingAccess),
                new CancelOpeningService($tx, $openingRepository, $openingAccess),
                $idempotency
            ),
            new BookingController(
                new CreateBookingService($tx, $openingRepository, new PdoBookingRepository($this->pdo)),
                new GetBookingService(new PdoBookingRepository($this->pdo), new PdoPaymentRepository($this->pdo), $providerRepository),
                new ListMyBookingsService(new PdoBookingRepository($this->pdo)),
                new MarkNoShowService(
                    $tx,
                    new PdoBookingRepository($this->pdo),
                    $providerRepository,
                    new RequestRefundService(new PdoPaymentRepository($this->pdo), new PdoRefundRepository($this->pdo)),
                    new PdoAuditLogger($this->pdo),
                    new PdoOutboxMessageStore($this->pdo)
                ),
                $idempotency
            ),
            new PaymentController(
                new InitiatePaymentService(new PdoBookingRepository($this->pdo), new PdoPaymentRepository($this->pdo), new StubStripeGateway()),
                new GetPaymentService(new PdoPaymentRepository($this->pdo), $providerRepository),
                $settlement = new SettlePaymentOutcomeService($tx, new PdoPaymentRepository($this->pdo), new PdoBookingRepository($this->pdo), $openingRepository),
                $idempotency
            ),
            new RefundController(
                new ListBookingRefundsService(new PdoRefundRepository($this->pdo), new PdoBookingRepository($this->pdo), $providerRepository),
                new ApproveRefundService($tx, new PdoRefundRepository($this->pdo), new PdoAuditLogger($this->pdo), new PdoOutboxMessageStore($this->pdo)),
                $idempotency
            ),
            new OfferingController(
                new CreateOfferingService(new PdoOfferingRepository($this->pdo), new OfferingAccessService($providerRepository)),
                new ListOfferingsService(new PdoOfferingRepository($this->pdo), new OfferingAccessService($providerRepository)),
                new UpdateOfferingService(new PdoOfferingRepository($this->pdo), new OfferingAccessService($providerRepository)),
                $idempotency
            ),
            new OrganizationController(
                new CreateOrganizationSelfService($tx, new PdoOrganizationRepository($this->pdo), new PdoOrganizationMemberRepository($this->pdo)),
                new ViewOrganizationService(new PdoOrganizationRepository($this->pdo), new PdoOrganizationMemberRepository($this->pdo)),
                new AddOrganizationMemberService(new PdoOrganizationRepository($this->pdo), new PdoOrganizationMemberRepository($this->pdo)),
                $idempotency
            ),
            new AdminOpsController(
                new AdminOpsQueryService(new PdoAdminOpsReadRepository($this->pdo)),
                new ForceExpireOpeningService($tx, $openingRepository, new PdoAuditLogger($this->pdo)),
                $idempotency
            ),
            new StripeWebhookController(new StripeSignatureVerifier('test_webhook_secret'), new PdoStripeWebhookEventRepository($this->pdo), new StripeWebhookDispatcher($settlement)),
        );
    }

    public function testOpeningCreationIsIdempotent(): void
    {
        $headers = $this->actorHeaders(['provider']);
        $headers['Idempotency-Key'] = 'idem-opening-1';
        $payload = [
            'service_offering_id' => 'offering-1',
            'starts_at' => '2026-03-29T12:00:00Z',
            'ends_at' => '2026-03-29T12:30:00Z',
            'price_override' => ['currency' => 'EUR', 'amount_minor' => 2200],
        ];

        $request = new Request('POST', '/api/v1/providers/provider-1/openings', $headers, $payload);
        $first = $this->router->dispatch($request);
        self::assertSame(201, $first->statusCode);
        self::assertSame('draft', $first->body['data']['status']);
        self::assertFalse($first->body['meta']['idempotency_replayed']);

        $second = $this->router->dispatch($request);
        self::assertSame(201, $second->statusCode);
        self::assertTrue($second->body['meta']['idempotency_replayed']);
        self::assertSame($first->body['data']['opening_id'], $second->body['data']['opening_id']);
    }

    public function testProviderCanListAndGetOwnOpenings(): void
    {
        $headers = $this->actorHeaders(['provider']);

        $list = $this->router->dispatch(new Request('GET', '/api/v1/providers/provider-1/openings?status=published', $headers, []));
        self::assertSame(200, $list->statusCode);
        self::assertCount(2, $list->body['data']);

        $get = $this->router->dispatch(new Request('GET', '/api/v1/providers/provider-1/openings/opening-1', $headers, []));
        self::assertSame(200, $get->statusCode);
        self::assertSame('opening-1', $get->body['data']['opening_id']);
        self::assertSame('published', $get->body['data']['status']);
    }

    public function testProviderCanPublishDraftOpening(): void
    {
        $this->pdo->exec("INSERT INTO openings (id, provider_id, service_offering_id, starts_at, ends_at, timezone, capacity, status, published_at, cancelled_at, created_at, updated_at, price_amount, price_currency) VALUES ('opening-draft-1', 'provider-1', 'offering-1', '2026-03-29T13:00:00Z', '2026-03-29T13:30:00Z', 'UTC', 1, 'draft', NULL, NULL, '2026-03-26T10:00:00Z', '2026-03-26T10:00:00Z', 2200, 'EUR')");

        $headers = $this->actorHeaders(['provider']);
        $headers['Idempotency-Key'] = 'idem-opening-publish-1';
        $response = $this->router->dispatch(new Request('POST', '/api/v1/providers/provider-1/openings/opening-draft-1:publish', $headers, []));

        self::assertSame(200, $response->statusCode);
        self::assertSame('published', $response->body['data']['status']);
        self::assertNotNull($response->body['data']['published_at']);
    }

    public function testProviderCanCancelPublishedOpening(): void
    {
        $headers = $this->actorHeaders(['provider']);
        $headers['Idempotency-Key'] = 'idem-opening-cancel-1';
        $response = $this->router->dispatch(new Request('POST', '/api/v1/providers/provider-1/openings/opening-2:cancel', $headers, []));

        self::assertSame(200, $response->statusCode);
        self::assertSame('cancelled_by_provider', $response->body['data']['status']);
        self::assertNotNull($response->body['data']['cancelled_at']);
    }

    public function testPublicCanListPublishedOpenings(): void
    {
        $response = $this->router->dispatch(new Request('GET', '/api/v1/public/openings?provider_id=provider-1&max_price_minor=2300', [], []));

        self::assertSame(200, $response->statusCode);
        self::assertCount(2, $response->body['data']);
        self::assertSame('published', $response->body['data'][0]['status']);
    }

    public function testBookingCreationIsIdempotent(): void
    {
        $headers = $this->actorHeaders(['client']);
        $headers['Idempotency-Key'] = 'idem-booking-1';

        $request = new Request('POST', '/api/v1/bookings', $headers, ['opening_id' => 'opening-1', 'client_note' => 'test']);
        $first = $this->router->dispatch($request);
        self::assertSame(201, $first->statusCode);
        self::assertFalse($first->body['meta']['idempotency_replayed']);

        $second = $this->router->dispatch($request);
        self::assertSame(201, $second->statusCode);
        self::assertTrue($second->body['meta']['idempotency_replayed']);
        self::assertSame($first->body['data']['booking_id'], $second->body['data']['booking_id']);
    }

    public function testClientCanReadOwnBookingWithPaymentSummary(): void
    {
        $headers = $this->actorHeaders(['client']);
        $headers['Idempotency-Key'] = 'idem-booking-read-1';
        $created = $this->router->dispatch(new Request('POST', '/api/v1/bookings', $headers, ['opening_id' => 'opening-1']));
        self::assertSame(201, $created->statusCode);
        $bookingId = $created->body['data']['booking_id'];

        $response = $this->router->dispatch(new Request('GET', "/api/v1/bookings/$bookingId", $this->actorHeaders(['client']), []));
        self::assertSame(200, $response->statusCode);
        self::assertSame($bookingId, $response->body['data']['booking_id']);
        self::assertSame('reserved', $response->body['data']['state']);
        self::assertSame(['currency' => 'EUR', 'amount_minor' => 2200], $response->body['data']['amount']);
        self::assertNull($response->body['data']['payment']);

        $payHeaders = $this->actorHeaders(['client']);
        $payHeaders['Idempotency-Key'] = 'idem-booking-read-pay-1';
        $payment = $this->router->dispatch(new Request('POST', "/api/v1/bookings/$bookingId/payments/initiate", $payHeaders, ['payment_method_type' => 'card']));
        self::assertSame(201, $payment->statusCode);

        $withPayment = $this->router->dispatch(new Request('GET', "/api/v1/bookings/$bookingId", $this->actorHeaders(['client']), []));
        self::assertSame(200, $withPayment->statusCode);
        self::assertSame('initiated', $withPayment->body['data']['payment']['state']);
        self::assertSame(['currency' => 'EUR', 'amount_minor' => 2200], $withPayment->body['data']['payment']['amount']);
    }

    public function testAdminCanReadAnyBooking(): void
    {
        $headers = $this->actorHeaders(['client']);
        $headers['Idempotency-Key'] = 'idem-booking-read-2';
        $created = $this->router->dispatch(new Request('POST', '/api/v1/bookings', $headers, ['opening_id' => 'opening-1']));
        $bookingId = $created->body['data']['booking_id'];

        $response = $this->router->dispatch(new Request('GET', "/api/v1/bookings/$bookingId", $this->actorHeaders(['admin']), []));
        self::assertSame(200, $response->statusCode);
        self::assertSame($bookingId, $response->body['data']['booking_id']);
    }

    public function testBookingReadDeniedForUnrelatedActor(): void
    {
        $headers = $this->actorHeaders(['client']);
        $headers['Idempotency-Key'] = 'idem-booking-read-3';
        $created = $this->router->dispatch(new Request('POST', '/api/v1/bookings', $headers, ['opening_id' => 'opening-1']));
        $bookingId = $created->body['data']['booking_id'];

        $otherHeaders = [
            'X-Actor-Id' => 'actor-2',
            'X-Actor-Subject' => 'sso|user_2',
            'X-Actor-Roles' => 'client',
            'X-User-Profile-Id' => 'profile-client-2',
        ];
        $response = $this->router->dispatch(new Request('GET', "/api/v1/bookings/$bookingId", $otherHeaders, []));
        self::assertSame(403, $response->statusCode);
        self::assertSame('FORBIDDEN_BOOKING_SCOPE', $response->body['error']['code']);
    }

    public function testUnknownBookingReturnsNotFound(): void
    {
        $response = $this->router->dispatch(new Request('GET', '/api/v1/bookings/missing-booking', $this->actorHeaders(['client']), []));
        self::assertSame(404, $response->statusCode);
        self::assertSame('BOOKING_NOT_FOUND', $response->body['error']['code']);
    }

    public function testClientCanReadOwnPaymentStatus(): void
    {
        $headers = $this->actorHeaders(['client']);
        $headers['Idempotency-Key'] = 'idem-payment-read-1';
        $created = $this->router->dispatch(new Request('POST', '/api/v1/bookings', $headers, ['opening_id' => 'opening-1']));
        $bookingId = $created->body['data']['booking_id'];

        $payHeaders = $this->actorHeaders(['client']);
        $payHeaders['Idempotency-Key'] = 'idem-payment-read-pay-1';
        $payment = $this->router->dispatch(new Request('POST', "/api/v1/bookings/$bookingId/payments/initiate", $payHeaders, ['payment_method_type' => 'card']));
        $paymentId = $payment->body['data']['payment_id'];

        $response = $this->router->dispatch(new Request('GET', "/api/v1/payments/$paymentId", $this->actorHeaders(['client']), []));
        self::assertSame(200, $response->statusCode);
        self::assertSame($paymentId, $response->body['data']['payment_id']);
        self::assertSame($bookingId, $response->body['data']['booking_id']);
        self::assertSame('initiated', $response->body['data']['state']);
        self::assertSame(['currency' => 'EUR', 'amount_minor' => 2200], $response->body['data']['amount']);
        self::assertNotNull($response->body['data']['stripe_payment_intent_id']);

        $scoped = $this->router->dispatch(new Request('GET', "/api/v1/bookings/$bookingId/payments/$paymentId", $this->actorHeaders(['client']), []));
        self::assertSame(200, $scoped->statusCode);
        self::assertSame($paymentId, $scoped->body['data']['payment_id']);

        $wrongBooking = $this->router->dispatch(new Request('GET', "/api/v1/bookings/other-booking/payments/$paymentId", $this->actorHeaders(['client']), []));
        self::assertSame(404, $wrongBooking->statusCode);
        self::assertSame('PAYMENT_NOT_FOUND', $wrongBooking->body['error']['code']);
    }

    public function testPaymentReadDeniedForUnrelatedActor(): void
    {
        $headers = $this->actorHeaders(['client']);
        $headers['Idempotency-Key'] = 'idem-payment-read-2';
        $created = $this->router->dispatch(new Request('POST', '/api/v1/bookings', $headers, ['opening_id' => 'opening-1']));
        $bookingId = $created->body['data']['booking_id'];

        $payHeaders = $this->actorHeaders(['client']);
        $payHeaders['Idempotency-Key'] = 'idem-payment-read-pay-2';
        $payment = $this->router->dispatch(new Request('POST', "/api/v1/bookings/$bookingId/payments/initiate", $payHeaders, ['payment_method_type' => 'card']));
        $paymentId = $payment->body['data']['payment_id'];

        $otherHeaders = [
            'X-Actor-Id' => 'actor-2',
            'X-Actor-Subject' => 'sso|user_2',
            'X-Actor-Roles' => 'client',
            'X-User-Profile-Id' => 'profile-client-2',
        ];
        $denied = $this->router->dispatch(new Request('GET', "/api/v1/payments/$paymentId", $otherHeaders, []));
        self::assertSame(403, $denied->statusCode);
        self::assertSame('FORBIDDEN_PAYMENT_SCOPE', $denied->body['error']['code']);

        $admin = $this->router->dispatch(new Request('GET', "/api/v1/payments/$paymentId", $this->actorHeaders(['admin']), []));
        self::assertSame(200, $admin->statusCode);

        $missing = $this->router->dispatch(new Request('GET', '/api/v1/payments/missing-payment', $this->actorHeaders(['client']), []));
        self::assertSame(404, $missing->statusCode);
    }

    public function testProviderCanMarkProviderNoShowIdempotently(): void
    {
        $bookingId = $this->seedConfirmedBooking('booking-noshow-1');

        $headers = $this->actorHeaders(['provider']);
        $headers['Idempotency-Key'] = 'idem-noshow-provider-1';
        $request = new Request('POST', "/api/v1/bookings/$bookingId:mark-provider-no-show", $headers, []);

        $first = $this->router->dispatch($request);
        self::assertSame(200, $first->statusCode);
        self::assertSame('provider_no_show', $first->body['data']['state']);
        self::assertSame('provider', $first->body['data']['no_show_actor']);
        self::assertNotNull($first->body['data']['no_show_recorded_at']);

        $second = $this->router->dispatch($request);
        self::assertSame(200, $second->statusCode);
        self::assertTrue($second->body['meta']['idempotency_replayed']);
    }

    public function testAdminCanMarkClientNoShow(): void
    {
        $bookingId = $this->seedConfirmedBooking('booking-noshow-2');

        $headers = $this->actorHeaders(['admin']);
        $headers['Idempotency-Key'] = 'idem-noshow-client-1';
        $response = $this->router->dispatch(new Request('POST', "/api/v1/bookings/$bookingId:mark-client-no-show", $headers, []));

        self::assertSame(200, $response->statusCode);
        self::assertSame('client_no_show', $response->body['data']['state']);
        self::assertSame('client', $response->body['data']['no_show_actor']);
    }

    public function testNoShowRejectedForNonConfirmedBooking(): void
    {
        $headers = $this->actorHeaders(['client']);
        $headers['Idempotency-Key'] = 'idem-noshow-reserved-1';
        $created = $this->router->dispatch(new Request('POST', '/api/v1/bookings', $headers, ['opening_id' => 'opening-1']));
        $bookingId = $created->body['data']['booking_id'];

        $markHeaders = $this->actorHeaders(['provider']);
        $markHeaders['Idempotency-Key'] = 'idem-noshow-reserved-2';
        $response = $this->router->dispatch(new Request('POST', "/api/v1/bookings/$bookingId:mark-provider-no-show", $markHeaders, []));

        self::assertSame(409, $response->statusCode);
        self::assertSame('BOOKING_STATE_INVALID', $response->body['error']['code']);
    }

    public function testNoShowDeniedForClientRole(): void
    {
        $bookingId = $this->seedConfirmedBooking('booking-noshow-3');

        $headers = $this->actorHeaders(['client']);
        $headers['Idempotency-Key'] = 'idem-noshow-denied-1';
        $response = $this->router->dispatch(new Request('POST', "/api/v1/bookings/$bookingId:mark-client-no-show", $headers, []));

        self::assertSame(403, $response->statusCode);
        self::assertSame('FORBIDDEN_ROLE_MISSING', $response->body['error']['code']);
    }

    public function testProviderNoShowTriggersRefundRequest(): void
    {
        $bookingId = $this->seedConfirmedBooking('booking-refund-1');
        $this->seedPaymentForBooking('payment-refund-1', $bookingId);

        $headers = $this->actorHeaders(['provider']);
        $headers['Idempotency-Key'] = 'idem-refund-noshow-1';
        $marked = $this->router->dispatch(new Request('POST', "/api/v1/bookings/$bookingId:mark-provider-no-show", $headers, []));
        self::assertSame(200, $marked->statusCode);

        $refunds = $this->router->dispatch(new Request('GET', "/api/v1/bookings/$bookingId/refunds", $this->actorHeaders(['provider']), []));
        self::assertSame(200, $refunds->statusCode);
        self::assertCount(1, $refunds->body['data']);
        self::assertSame('requested', $refunds->body['data'][0]['state']);
        self::assertSame('provider_no_show', $refunds->body['data'][0]['reason']);
        self::assertSame('payment-refund-1', $refunds->body['data'][0]['payment_id']);
        self::assertSame(['currency' => 'EUR', 'amount_minor' => 2200], $refunds->body['data'][0]['amount']);
    }

    public function testClientNoShowDoesNotCreateRefund(): void
    {
        $bookingId = $this->seedConfirmedBooking('booking-refund-2');
        $this->seedPaymentForBooking('payment-refund-2', $bookingId);

        $headers = $this->actorHeaders(['provider']);
        $headers['Idempotency-Key'] = 'idem-refund-noshow-2';
        $marked = $this->router->dispatch(new Request('POST', "/api/v1/bookings/$bookingId:mark-client-no-show", $headers, []));
        self::assertSame(200, $marked->statusCode);

        $refunds = $this->router->dispatch(new Request('GET', "/api/v1/bookings/$bookingId/refunds", $this->actorHeaders(['client']), []));
        self::assertSame(200, $refunds->statusCode);
        self::assertCount(0, $refunds->body['data']);
    }

    public function testProviderNoShowWithoutPaymentCreatesNoRefund(): void
    {
        $bookingId = $this->seedConfirmedBooking('booking-refund-3');

        $headers = $this->actorHeaders(['provider']);
        $headers['Idempotency-Key'] = 'idem-refund-noshow-3';
        $marked = $this->router->dispatch(new Request('POST', "/api/v1/bookings/$bookingId:mark-provider-no-show", $headers, []));
        self::assertSame(200, $marked->statusCode);

        $refunds = $this->router->dispatch(new Request('GET', "/api/v1/bookings/$bookingId/refunds", $this->actorHeaders(['admin']), []));
        self::assertSame(200, $refunds->statusCode);
        self::assertCount(0, $refunds->body['data']);
    }

    public function testAdminCanApproveRequestedRefundIdempotently(): void
    {
        $bookingId = $this->seedConfirmedBooking('booking-refund-4');
        $this->seedPaymentForBooking('payment-refund-4', $bookingId);

        $noShowHeaders = $this->actorHeaders(['provider']);
        $noShowHeaders['Idempotency-Key'] = 'idem-refund-noshow-4';
        $this->router->dispatch(new Request('POST', "/api/v1/bookings/$bookingId:mark-provider-no-show", $noShowHeaders, []));

        $refunds = $this->router->dispatch(new Request('GET', "/api/v1/bookings/$bookingId/refunds", $this->actorHeaders(['admin']), []));
        $refundId = $refunds->body['data'][0]['refund_id'];

        $denied = $this->router->dispatch(new Request('POST', "/api/v1/refunds/$refundId:approve", $this->actorHeaders(['provider']) + ['Idempotency-Key' => 'idem-refund-approve-denied'], []));
        self::assertSame(403, $denied->statusCode);

        $approveHeaders = $this->actorHeaders(['admin']);
        $approveHeaders['Idempotency-Key'] = 'idem-refund-approve-1';
        $request = new Request('POST', "/api/v1/refunds/$refundId:approve", $approveHeaders, ['note' => 'Provider no-show confirmed by support.']);

        $first = $this->router->dispatch($request);
        self::assertSame(200, $first->statusCode);
        self::assertSame('pending', $first->body['data']['state']);
        self::assertSame('actor-1', $first->body['data']['decided_by_actor_id']);
        self::assertSame('Provider no-show confirmed by support.', $first->body['data']['decision_note']);

        $second = $this->router->dispatch($request);
        self::assertSame(200, $second->statusCode);
        self::assertTrue($second->body['meta']['idempotency_replayed']);

        $retryHeaders = $this->actorHeaders(['admin']);
        $retryHeaders['Idempotency-Key'] = 'idem-refund-approve-2';
        $conflict = $this->router->dispatch(new Request('POST', "/api/v1/refunds/$refundId:approve", $retryHeaders, []));
        self::assertSame(409, $conflict->statusCode);
        self::assertSame('REFUND_STATE_INVALID', $conflict->body['error']['code']);
    }

    public function testRefundListDeniedForUnrelatedActor(): void
    {
        $bookingId = $this->seedConfirmedBooking('booking-refund-5');
        $this->seedPaymentForBooking('payment-refund-5', $bookingId);

        $otherHeaders = [
            'X-Actor-Id' => 'actor-2',
            'X-Actor-Subject' => 'sso|user_2',
            'X-Actor-Roles' => 'client',
            'X-User-Profile-Id' => 'profile-client-2',
        ];
        $response = $this->router->dispatch(new Request('GET', "/api/v1/bookings/$bookingId/refunds", $otherHeaders, []));
        self::assertSame(403, $response->statusCode);
        self::assertSame('FORBIDDEN_REFUND_SCOPE', $response->body['error']['code']);
    }

    public function testProviderCanCreateAndListOfferings(): void
    {
        $headers = $this->actorHeaders(['provider']);
        $headers['Idempotency-Key'] = 'idem-offering-create-1';
        $request = new Request('POST', '/api/v1/providers/provider-1/offerings', $headers, [
            'name' => 'Massage - 45 min',
            'description' => 'Relaxing massage',
            'duration_minutes' => 45,
            'base_price' => ['currency' => 'eur', 'amount_minor' => 3500],
        ]);

        $first = $this->router->dispatch($request);
        self::assertSame(201, $first->statusCode);
        self::assertSame('Massage - 45 min', $first->body['data']['name']);
        self::assertSame('active', $first->body['data']['status']);
        self::assertSame(['currency' => 'EUR', 'amount_minor' => 3500], $first->body['data']['base_price']);

        $second = $this->router->dispatch($request);
        self::assertSame(201, $second->statusCode);
        self::assertTrue($second->body['meta']['idempotency_replayed']);

        $list = $this->router->dispatch(new Request('GET', '/api/v1/providers/provider-1/offerings', $this->actorHeaders(['provider']), []));
        self::assertSame(200, $list->statusCode);
        self::assertCount(2, $list->body['data']);
    }

    public function testOfferingCreationValidation(): void
    {
        $headers = $this->actorHeaders(['provider']);
        $headers['Idempotency-Key'] = 'idem-offering-invalid-1';
        $shortDuration = $this->router->dispatch(new Request('POST', '/api/v1/providers/provider-1/offerings', $headers, [
            'name' => 'Too short',
            'duration_minutes' => 3,
            'base_price' => ['currency' => 'EUR', 'amount_minor' => 1000],
        ]));
        self::assertSame(422, $shortDuration->statusCode);
        self::assertSame('VALIDATION_DURATION_INVALID', $shortDuration->body['error']['code']);

        $headers['Idempotency-Key'] = 'idem-offering-invalid-2';
        $badPrice = $this->router->dispatch(new Request('POST', '/api/v1/providers/provider-1/offerings', $headers, [
            'name' => 'Bad price',
            'duration_minutes' => 30,
            'base_price' => ['amount_minor' => 1000],
        ]));
        self::assertSame(422, $badPrice->statusCode);
        self::assertSame('VALIDATION_PRICE_INVALID', $badPrice->body['error']['code']);
    }

    public function testProviderCanUpdateOffering(): void
    {
        $response = $this->router->dispatch(new Request('PATCH', '/api/v1/providers/provider-1/offerings/offering-1', $this->actorHeaders(['provider']), [
            'name' => 'Haircut deluxe',
            'base_price' => ['currency' => 'EUR', 'amount_minor' => 2700],
            'status' => 'inactive',
        ]));

        self::assertSame(200, $response->statusCode);
        self::assertSame('Haircut deluxe', $response->body['data']['name']);
        self::assertSame('inactive', $response->body['data']['status']);
        self::assertSame(['currency' => 'EUR', 'amount_minor' => 2700], $response->body['data']['base_price']);

        $missing = $this->router->dispatch(new Request('PATCH', '/api/v1/providers/provider-1/offerings/missing-offering', $this->actorHeaders(['provider']), ['name' => 'X']));
        self::assertSame(404, $missing->statusCode);
        self::assertSame('OFFERING_NOT_FOUND', $missing->body['error']['code']);
    }

    public function testOfferingRepositoryRejectsUnknownUpdateColumn(): void
    {
        // Defense-in-depth: the repository must never write a column key it was
        // not designed to update, even if a future caller forwards a bad key.
        $repo = new PdoOfferingRepository($this->pdo);

        $this->expectException(\InvalidArgumentException::class);
        $repo->update('offering-1', ['provider_id' => 'attacker-owned-provider']);
    }

    public function testPublicOfferingsListShowsOnlyActive(): void
    {
        $deactivate = $this->router->dispatch(new Request('PATCH', '/api/v1/providers/provider-1/offerings/offering-1', $this->actorHeaders(['provider']), ['status' => 'inactive']));
        self::assertSame(200, $deactivate->statusCode);

        $response = $this->router->dispatch(new Request('GET', '/api/v1/public/providers/provider-1/offerings', [], []));
        self::assertSame(200, $response->statusCode);
        self::assertCount(0, $response->body['data']);
    }

    public function testOfferingManagementDeniedForUnrelatedProvider(): void
    {
        $otherHeaders = [
            'X-Actor-Id' => 'actor-2',
            'X-Actor-Subject' => 'sso|user_2',
            'X-Actor-Roles' => 'provider',
            'X-User-Profile-Id' => 'profile-client-2',
            'Idempotency-Key' => 'idem-offering-denied-1',
        ];
        $response = $this->router->dispatch(new Request('POST', '/api/v1/providers/provider-1/offerings', $otherHeaders, [
            'name' => 'Not mine',
            'duration_minutes' => 30,
            'base_price' => ['currency' => 'EUR', 'amount_minor' => 1000],
        ]));

        self::assertSame(403, $response->statusCode);
        self::assertSame('FORBIDDEN_PROVIDER_ACCESS', $response->body['error']['code']);
    }

    public function testProviderCanReadAndUpdateOwnProvider(): void
    {
        $response = $this->router->dispatch(new Request('GET', '/api/v1/providers/provider-1', $this->actorHeaders(['provider']), []));
        self::assertSame(200, $response->statusCode);
        self::assertSame('provider-1', $response->body['data']['provider_id']);
        self::assertSame('individual', $response->body['data']['provider_type']);

        $updated = $this->router->dispatch(new Request('PATCH', '/api/v1/providers/provider-1', $this->actorHeaders(['provider']), ['display_name' => 'Ana Studio']));
        self::assertSame(200, $updated->statusCode);
        self::assertSame('Ana Studio', $updated->body['data']['display_name']);

        $statusDenied = $this->router->dispatch(new Request('PATCH', '/api/v1/providers/provider-1', $this->actorHeaders(['provider']), ['status' => 'suspended']));
        self::assertSame(403, $statusDenied->statusCode);
        self::assertSame('FORBIDDEN_PROVIDER_STATUS_CHANGE', $statusDenied->body['error']['code']);

        $statusByAdmin = $this->router->dispatch(new Request('PATCH', '/api/v1/providers/provider-1', $this->actorHeaders(['admin']), ['status' => 'suspended']));
        self::assertSame(200, $statusByAdmin->statusCode);
        self::assertSame('suspended', $statusByAdmin->body['data']['status']);
    }

    public function testProviderLinkSelfService(): void
    {
        $newActorHeaders = [
            'X-Actor-Id' => 'actor-2',
            'X-Actor-Subject' => 'sso|user_2',
            'X-Actor-Roles' => 'provider',
            'X-User-Profile-Id' => 'profile-client-2',
            'Idempotency-Key' => 'idem-provider-link-1',
        ];
        $request = new Request('POST', '/api/v1/me/provider-link', $newActorHeaders, ['provider_type' => 'individual', 'display_name' => 'Marko Frizer']);

        $first = $this->router->dispatch($request);
        self::assertSame(201, $first->statusCode);
        self::assertSame('individual', $first->body['data']['provider_type']);
        self::assertSame('Marko Frizer', $first->body['data']['display_name']);

        $second = $this->router->dispatch($request);
        self::assertSame(201, $second->statusCode);
        self::assertTrue($second->body['meta']['idempotency_replayed']);

        $alreadyLinked = $this->actorHeaders(['provider']);
        $alreadyLinked['Idempotency-Key'] = 'idem-provider-link-2';
        $conflict = $this->router->dispatch(new Request('POST', '/api/v1/me/provider-link', $alreadyLinked, ['provider_type' => 'individual']));
        self::assertSame(409, $conflict->statusCode);
        self::assertSame('CONFLICT_PROVIDER_ALREADY_LINKED', $conflict->body['error']['code']);
    }

    public function testProviderCanCreateOrganizationSelfService(): void
    {
        $headers = $this->actorHeaders(['provider']);
        $headers['Idempotency-Key'] = 'idem-org-create-1';
        $request = new Request('POST', '/api/v1/organizations', $headers, [
            'legal_name' => 'Studio Brzo d.o.o.',
            'display_name' => 'Studio Brzo',
            'contact_email' => 'studio@example.test',
            'contact_phone' => '+385911112222',
        ]);

        $first = $this->router->dispatch($request);
        self::assertSame(201, $first->statusCode);
        $orgId = $first->body['data']['organization_id'];
        self::assertCount(1, $first->body['data']['members']);
        self::assertSame('owner', $first->body['data']['members'][0]['organization_role']);
        self::assertSame('profile-client-1', $first->body['data']['members'][0]['user_profile_id']);

        $second = $this->router->dispatch($request);
        self::assertTrue($second->body['meta']['idempotency_replayed']);

        $view = $this->router->dispatch(new Request('GET', "/api/v1/organizations/$orgId", $this->actorHeaders(['provider']), []));
        self::assertSame(200, $view->statusCode);
        self::assertSame('Studio Brzo', $view->body['data']['display_name']);

        $outsider = [
            'X-Actor-Id' => 'actor-2',
            'X-Actor-Subject' => 'sso|user_2',
            'X-Actor-Roles' => 'provider',
            'X-User-Profile-Id' => 'profile-client-2',
        ];
        $denied = $this->router->dispatch(new Request('GET', "/api/v1/organizations/$orgId", $outsider, []));
        self::assertSame(403, $denied->statusCode);
        self::assertSame('FORBIDDEN_ORGANIZATION_ACCESS', $denied->body['error']['code']);
    }

    public function testOrganizationMemberManagement(): void
    {
        $headers = $this->actorHeaders(['provider']);
        $headers['Idempotency-Key'] = 'idem-org-create-2';
        $created = $this->router->dispatch(new Request('POST', '/api/v1/organizations', $headers, [
            'legal_name' => 'Members d.o.o.',
            'display_name' => 'Members Studio',
            'contact_email' => 'members@example.test',
            'contact_phone' => '+385911113333',
        ]));
        $orgId = $created->body['data']['organization_id'];

        $addHeaders = $this->actorHeaders(['provider']);
        $addHeaders['Idempotency-Key'] = 'idem-org-member-1';
        $added = $this->router->dispatch(new Request('POST', "/api/v1/organizations/$orgId/members", $addHeaders, [
            'user_profile_id' => 'profile-client-2',
            'organization_role' => 'staff',
        ]));
        self::assertSame(201, $added->statusCode);
        self::assertSame('staff', $added->body['data']['organization_role']);

        $dupHeaders = $this->actorHeaders(['provider']);
        $dupHeaders['Idempotency-Key'] = 'idem-org-member-2';
        $duplicate = $this->router->dispatch(new Request('POST', "/api/v1/organizations/$orgId/members", $dupHeaders, [
            'user_profile_id' => 'profile-client-2',
            'organization_role' => 'manager',
        ]));
        self::assertSame(409, $duplicate->statusCode);
        self::assertSame('CONFLICT_MEMBER_ALREADY_EXISTS', $duplicate->body['error']['code']);

        $staffActor = [
            'X-Actor-Id' => 'actor-2',
            'X-Actor-Subject' => 'sso|user_2',
            'X-Actor-Roles' => 'provider',
            'X-User-Profile-Id' => 'profile-client-2',
            'Idempotency-Key' => 'idem-org-member-3',
        ];
        $deniedAdd = $this->router->dispatch(new Request('POST', "/api/v1/organizations/$orgId/members", $staffActor, [
            'user_profile_id' => 'profile-client-3',
            'organization_role' => 'staff',
        ]));
        self::assertSame(403, $deniedAdd->statusCode);
        self::assertSame('FORBIDDEN_POLICY_DENIED', $deniedAdd->body['error']['code']);

        $memberView = $this->router->dispatch(new Request('GET', "/api/v1/organizations/$orgId", [
            'X-Actor-Id' => 'actor-2',
            'X-Actor-Subject' => 'sso|user_2',
            'X-Actor-Roles' => 'provider',
            'X-User-Profile-Id' => 'profile-client-2',
        ], []));
        self::assertSame(200, $memberView->statusCode);
        self::assertCount(2, $memberView->body['data']['members']);
    }

    public function testAdminOperationalLists(): void
    {
        $bookingId = $this->seedConfirmedBooking('booking-adminops-1');
        $this->seedPaymentForBooking('payment-adminops-1', $bookingId);

        $noShowHeaders = $this->actorHeaders(['provider']);
        $noShowHeaders['Idempotency-Key'] = 'idem-adminops-noshow-1';
        $this->router->dispatch(new Request('POST', "/api/v1/bookings/$bookingId:mark-provider-no-show", $noShowHeaders, []));

        $bookings = $this->router->dispatch(new Request('GET', '/api/v1/admin/bookings?state=provider_no_show', $this->actorHeaders(['admin']), []));
        self::assertSame(200, $bookings->statusCode);
        self::assertCount(1, $bookings->body['data']);
        self::assertSame($bookingId, $bookings->body['data'][0]['booking_id']);
        self::assertSame('captured', $bookings->body['data'][0]['payment']['state']);

        $payments = $this->router->dispatch(new Request('GET', '/api/v1/admin/payments', $this->actorHeaders(['admin']), []));
        self::assertSame(200, $payments->statusCode);
        self::assertCount(1, $payments->body['data']);

        $refunds = $this->router->dispatch(new Request('GET', '/api/v1/admin/refunds?reason=provider_no_show', $this->actorHeaders(['admin']), []));
        self::assertSame(200, $refunds->statusCode);
        self::assertCount(1, $refunds->body['data']);
        self::assertSame('requested', $refunds->body['data'][0]['state']);

        $events = $this->router->dispatch(new Request('GET', '/api/v1/admin/webhooks/stripe/events', $this->actorHeaders(['admin']), []));
        self::assertSame(200, $events->statusCode);

        $denied = $this->router->dispatch(new Request('GET', '/api/v1/admin/bookings', $this->actorHeaders(['provider']), []));
        self::assertSame(403, $denied->statusCode);
    }

    public function testSuperAdminCanForceExpireOpening(): void
    {
        $headers = $this->actorHeaders(['super-admin']);
        $headers['Idempotency-Key'] = 'idem-force-expire-1';
        $request = new Request('POST', '/api/v1/admin/openings/opening-2:force-expire', $headers, []);

        $first = $this->router->dispatch($request);
        self::assertSame(200, $first->statusCode);
        self::assertSame('expired', $first->body['data']['status']);

        $second = $this->router->dispatch($request);
        self::assertTrue($second->body['meta']['idempotency_replayed']);

        $retryHeaders = $this->actorHeaders(['super-admin']);
        $retryHeaders['Idempotency-Key'] = 'idem-force-expire-2';
        $conflict = $this->router->dispatch(new Request('POST', '/api/v1/admin/openings/opening-2:force-expire', $retryHeaders, []));
        self::assertSame(409, $conflict->statusCode);
        self::assertSame('CONFLICT_OPENING_STATE_INVALID', $conflict->body['error']['code']);

        $adminHeaders = $this->actorHeaders(['admin']);
        $adminHeaders['Idempotency-Key'] = 'idem-force-expire-3';
        $denied = $this->router->dispatch(new Request('POST', '/api/v1/admin/openings/opening-1:force-expire', $adminHeaders, []));
        self::assertSame(403, $denied->statusCode);
    }

    public function testNoShowWritesAuditAndOutboxRecords(): void
    {
        $bookingId = $this->seedConfirmedBooking('booking-audit-1');
        $this->seedPaymentForBooking('payment-audit-1', $bookingId);

        $headers = $this->actorHeaders(['provider']);
        $headers['Idempotency-Key'] = 'idem-audit-noshow-1';
        $this->router->dispatch(new Request('POST', "/api/v1/bookings/$bookingId:mark-provider-no-show", $headers, []));

        $audit = $this->pdo->query("SELECT * FROM audit_log WHERE resource_type = 'booking' AND resource_id = '$bookingId'")->fetchAll(\PDO::FETCH_ASSOC);
        self::assertCount(1, $audit);
        self::assertSame('booking.mark-provider-no-show', $audit[0]['action']);
        self::assertSame('actor-1', $audit[0]['actor_id']);

        $outbox = $this->pdo->query("SELECT * FROM outbox_messages WHERE message_type = 'booking.provider_no_show'")->fetchAll(\PDO::FETCH_ASSOC);
        self::assertCount(1, $outbox);
        self::assertSame('pending', $outbox[0]['status']);
        $payload = json_decode((string) $outbox[0]['payload'], true);
        self::assertSame($bookingId, $payload['booking_id']);
    }

    public function testOutboxProcessorDispatchesAndFails(): void
    {
        $store = new PdoOutboxMessageStore($this->pdo);
        $okId = $store->enqueue('test.ok', ['value' => 1]);
        $boomId = $store->enqueue('test.boom', ['value' => 2]);
        $unknownId = $store->enqueue('test.unknown', []);

        $handled = [];
        $processor = new OutboxProcessor($store, maxAttempts: 2);
        $processor->register('test.ok', function (array $payload) use (&$handled): void {
            $handled[] = $payload['value'];
        });
        $processor->register('test.boom', function (): void {
            throw new \RuntimeException('handler exploded');
        });

        $first = $processor->processPending();
        self::assertSame(['dispatched' => 1, 'failed' => 1, 'skipped' => 1], $first);
        self::assertSame([1], $handled);

        $statuses = $this->pdo->query('SELECT id, status, attempts, last_error FROM outbox_messages')->fetchAll(\PDO::FETCH_ASSOC);
        $byId = array_column($statuses, null, 'id');
        self::assertSame('dispatched', $byId[$okId]['status']);
        self::assertSame('failed', $byId[$unknownId]['status']);
        self::assertSame('pending', $byId[$boomId]['status']);
        self::assertSame(1, (int) $byId[$boomId]['attempts']);

        $second = $processor->processPending();
        self::assertSame(['dispatched' => 0, 'failed' => 1, 'skipped' => 0], $second);
        $boomRow = $this->pdo->query("SELECT status, attempts FROM outbox_messages WHERE id = '$boomId'")->fetch(\PDO::FETCH_ASSOC);
        self::assertSame('failed', $boomRow['status']);
        self::assertSame(2, (int) $boomRow['attempts']);
    }

    public function testOutboxClaimIsExclusive(): void
    {
        $store = new PdoOutboxMessageStore($this->pdo);
        $id = $store->enqueue('test.exclusive', ['v' => 1]);

        $firstClaim = $store->claimPending(10);
        self::assertCount(1, $firstClaim);
        self::assertSame($id, $firstClaim[0]['message_id']);

        // A second drain (simulating a concurrent worker) must NOT re-claim the
        // already-in-flight message — otherwise its handler runs twice.
        $secondClaim = $store->claimPending(10);
        self::assertCount(0, $secondClaim);

        // After a transient failure the message returns to pending and is claimable again.
        $store->recordAttemptFailure($id, 'transient');
        $retryClaim = $store->claimPending(10);
        self::assertCount(1, $retryClaim);
        self::assertSame(1, $retryClaim[0]['attempts']);
    }

    public function testRefundApprovalWritesAuditAndOutbox(): void
    {
        $bookingId = $this->seedConfirmedBooking('booking-audit-2');
        $this->seedPaymentForBooking('payment-audit-2', $bookingId);

        $noShowHeaders = $this->actorHeaders(['provider']);
        $noShowHeaders['Idempotency-Key'] = 'idem-audit-noshow-2';
        $this->router->dispatch(new Request('POST', "/api/v1/bookings/$bookingId:mark-provider-no-show", $noShowHeaders, []));

        $refunds = $this->router->dispatch(new Request('GET', "/api/v1/bookings/$bookingId/refunds", $this->actorHeaders(['admin']), []));
        $refundId = $refunds->body['data'][0]['refund_id'];

        $approveHeaders = $this->actorHeaders(['admin']);
        $approveHeaders['Idempotency-Key'] = 'idem-audit-approve-1';
        $this->router->dispatch(new Request('POST', "/api/v1/refunds/$refundId:approve", $approveHeaders, ['note' => 'ok']));

        $audit = $this->pdo->query("SELECT * FROM audit_log WHERE action = 'refund.approve' AND resource_id = '$refundId'")->fetchAll(\PDO::FETCH_ASSOC);
        self::assertCount(1, $audit);

        $outbox = $this->pdo->query("SELECT * FROM outbox_messages WHERE message_type = 'refund.approved'")->fetchAll(\PDO::FETCH_ASSOC);
        self::assertCount(1, $outbox);
    }

    public function testSimulatedPaymentSuccessConfirmsBooking(): void
    {
        $headers = $this->actorHeaders(['client']);
        $headers['Idempotency-Key'] = 'idem-settle-1';
        $created = $this->router->dispatch(new Request('POST', '/api/v1/bookings', $headers, ['opening_id' => 'opening-1']));
        $bookingId = $created->body['data']['booking_id'];

        $payHeaders = $this->actorHeaders(['client']);
        $payHeaders['Idempotency-Key'] = 'idem-settle-pay-1';
        $payment = $this->router->dispatch(new Request('POST', "/api/v1/bookings/$bookingId/payments/initiate", $payHeaders, ['payment_method_type' => 'card']));
        $paymentId = $payment->body['data']['payment_id'];

        $settled = $this->router->dispatch(new Request('POST', "/api/v1/payments/$paymentId:simulate-succeed", $this->actorHeaders(['admin']), []));
        self::assertSame(200, $settled->statusCode);
        self::assertSame('captured', $settled->body['data']['state']);
        self::assertSame('confirmed', $settled->body['data']['booking']['state']);
        self::assertNotNull($settled->body['data']['booking']['confirmed_at']);

        $opening = $this->pdo->query("SELECT status FROM openings WHERE id = 'opening-1'")->fetchColumn();
        self::assertSame('booked', $opening);

        $again = $this->router->dispatch(new Request('POST', "/api/v1/payments/$paymentId:simulate-succeed", $this->actorHeaders(['admin']), []));
        self::assertSame(409, $again->statusCode);
        self::assertSame('PAYMENT_STATE_INVALID', $again->body['error']['code']);

        $denied = $this->router->dispatch(new Request('POST', "/api/v1/payments/$paymentId:simulate-succeed", $this->actorHeaders(['client']), []));
        self::assertSame(403, $denied->statusCode);
    }

    public function testSimulatedPaymentFailureReleasesOpening(): void
    {
        $headers = $this->actorHeaders(['client']);
        $headers['Idempotency-Key'] = 'idem-settle-2';
        $created = $this->router->dispatch(new Request('POST', '/api/v1/bookings', $headers, ['opening_id' => 'opening-2']));
        $bookingId = $created->body['data']['booking_id'];

        $payHeaders = $this->actorHeaders(['client']);
        $payHeaders['Idempotency-Key'] = 'idem-settle-pay-2';
        $payment = $this->router->dispatch(new Request('POST', "/api/v1/bookings/$bookingId/payments/initiate", $payHeaders, ['payment_method_type' => 'card']));
        $paymentId = $payment->body['data']['payment_id'];

        $settled = $this->router->dispatch(new Request('POST', "/api/v1/payments/$paymentId:simulate-fail", $this->actorHeaders(['admin']), ['reason' => 'card_declined']));
        self::assertSame(200, $settled->statusCode);
        self::assertSame('failed', $settled->body['data']['state']);
        self::assertSame('payment_failed', $settled->body['data']['booking']['state']);

        $opening = $this->pdo->query("SELECT status FROM openings WHERE id = 'opening-2'")->fetchColumn();
        self::assertSame('published', $opening);
    }

    public function testFullMoneyFlowBookPayConfirmNoShowRefund(): void
    {
        // book -> pay -> simulate success -> provider no-show -> refund requested -> admin approves
        $headers = $this->actorHeaders(['client']);
        $headers['Idempotency-Key'] = 'idem-e2e-1';
        $created = $this->router->dispatch(new Request('POST', '/api/v1/bookings', $headers, ['opening_id' => 'opening-1']));
        $bookingId = $created->body['data']['booking_id'];

        $payHeaders = $this->actorHeaders(['client']);
        $payHeaders['Idempotency-Key'] = 'idem-e2e-pay-1';
        $payment = $this->router->dispatch(new Request('POST', "/api/v1/bookings/$bookingId/payments/initiate", $payHeaders, ['payment_method_type' => 'card']));
        $paymentId = $payment->body['data']['payment_id'];

        $this->router->dispatch(new Request('POST', "/api/v1/payments/$paymentId:simulate-succeed", $this->actorHeaders(['admin']), []));

        $noShowHeaders = $this->actorHeaders(['provider']);
        $noShowHeaders['Idempotency-Key'] = 'idem-e2e-noshow-1';
        $marked = $this->router->dispatch(new Request('POST', "/api/v1/bookings/$bookingId:mark-provider-no-show", $noShowHeaders, []));
        self::assertSame(200, $marked->statusCode);
        self::assertSame('provider_no_show', $marked->body['data']['state']);

        $refunds = $this->router->dispatch(new Request('GET', "/api/v1/bookings/$bookingId/refunds", $this->actorHeaders(['admin']), []));
        self::assertCount(1, $refunds->body['data']);
        $refundId = $refunds->body['data'][0]['refund_id'];
        self::assertSame('requested', $refunds->body['data'][0]['state']);

        $approveHeaders = $this->actorHeaders(['admin']);
        $approveHeaders['Idempotency-Key'] = 'idem-e2e-approve-1';
        $approved = $this->router->dispatch(new Request('POST', "/api/v1/refunds/$refundId:approve", $approveHeaders, ['note' => 'e2e']));
        self::assertSame(200, $approved->statusCode);
        self::assertSame('pending', $approved->body['data']['state']);
    }

    public function testStripeWebhookSettlesPayment(): void
    {
        $headers = $this->actorHeaders(['client']);
        $headers['Idempotency-Key'] = 'idem-webhook-settle-1';
        $created = $this->router->dispatch(new Request('POST', '/api/v1/bookings', $headers, ['opening_id' => 'opening-1']));
        $bookingId = $created->body['data']['booking_id'];

        $payHeaders = $this->actorHeaders(['client']);
        $payHeaders['Idempotency-Key'] = 'idem-webhook-settle-pay-1';
        $payment = $this->router->dispatch(new Request('POST', "/api/v1/bookings/$bookingId/payments/initiate", $payHeaders, ['payment_method_type' => 'card']));
        $intentId = $payment->body['data']['stripe']['payment_intent_id'];

        $payload = json_encode(['id' => 'evt_settle_1', 'type' => 'payment_intent.succeeded', 'data' => ['object' => ['id' => $intentId]]], JSON_THROW_ON_ERROR);
        // Real Stripe signature scheme (t=...,v1=...), as sent by live webhooks.
        $timestamp = time();
        $signature = sprintf('t=%d,v1=%s', $timestamp, hash_hmac('sha256', $timestamp . '.' . $payload, 'test_webhook_secret'));
        $response = $this->router->dispatch(new Request('POST', '/api/v1/webhooks/stripe', ['Stripe-Signature' => $signature], json_decode($payload, true), [], $payload));
        self::assertSame(200, $response->statusCode);

        $booking = $this->router->dispatch(new Request('GET', "/api/v1/bookings/$bookingId", $this->actorHeaders(['client']), []));
        self::assertSame('confirmed', $booking->body['data']['state']);
        self::assertSame('captured', $booking->body['data']['payment']['state']);
    }

    public function testExpiredReservationIsSweptAndOpeningReleased(): void
    {
        $headers = $this->actorHeaders(['client']);
        $headers['Idempotency-Key'] = 'idem-expiry-1';
        $created = $this->router->dispatch(new Request('POST', '/api/v1/bookings', $headers, ['opening_id' => 'opening-1']));
        $bookingId = $created->body['data']['booking_id'];

        $sweep = new ExpireReservationsService(
            new PdoTransactionManager($this->pdo),
            new PdoBookingRepository($this->pdo),
            new PdoOpeningRepository($this->pdo),
            new PdoAuditLogger($this->pdo),
        );

        // Not yet expired: sweep must leave it alone.
        self::assertSame(['expired' => 0], $sweep->expireDue());

        $past = (new \DateTimeImmutable('-1 minute'))->format(DATE_ATOM);
        $this->pdo->exec("UPDATE bookings SET reservation_expires_at = '$past' WHERE id = '$bookingId'");

        self::assertSame(['expired' => 1], $sweep->expireDue());

        $booking = $this->pdo->query("SELECT state FROM bookings WHERE id = '$bookingId'")->fetchColumn();
        self::assertSame('reservation_expired', $booking);
        $opening = $this->pdo->query("SELECT status FROM openings WHERE id = 'opening-1'")->fetchColumn();
        self::assertSame('published', $opening);

        // The released slot is bookable again.
        $rebookHeaders = $this->actorHeaders(['client']);
        $rebookHeaders['Idempotency-Key'] = 'idem-expiry-2';
        $rebooked = $this->router->dispatch(new Request('POST', '/api/v1/bookings', $rebookHeaders, ['opening_id' => 'opening-1']));
        self::assertSame(201, $rebooked->statusCode);
    }

    public function testApprovedRefundIsExecutedAgainstGateway(): void
    {
        $bookingId = $this->seedConfirmedBooking('booking-exec-1');
        $this->seedPaymentForBooking('payment-exec-1', $bookingId);

        $noShowHeaders = $this->actorHeaders(['provider']);
        $noShowHeaders['Idempotency-Key'] = 'idem-exec-noshow-1';
        $this->router->dispatch(new Request('POST', "/api/v1/bookings/$bookingId:mark-provider-no-show", $noShowHeaders, []));

        $refunds = $this->router->dispatch(new Request('GET', "/api/v1/bookings/$bookingId/refunds", $this->actorHeaders(['admin']), []));
        $refundId = $refunds->body['data'][0]['refund_id'];

        $approveHeaders = $this->actorHeaders(['admin']);
        $approveHeaders['Idempotency-Key'] = 'idem-exec-approve-1';
        $this->router->dispatch(new Request('POST', "/api/v1/refunds/$refundId:approve", $approveHeaders, []));

        $execution = new ExecuteRefundService(
            new PdoTransactionManager($this->pdo),
            new PdoRefundRepository($this->pdo),
            new PdoPaymentRepository($this->pdo),
            new StubStripeGateway(),
            new PdoAuditLogger($this->pdo),
        );

        self::assertSame(['succeeded' => 1, 'failed' => 0], $execution->executePending());

        $refund = $this->pdo->query("SELECT state, stripe_refund_id FROM refunds WHERE id = '$refundId'")->fetch(\PDO::FETCH_ASSOC);
        self::assertSame('succeeded', $refund['state']);
        self::assertStringStartsWith('re_', (string) $refund['stripe_refund_id']);

        $payment = $this->pdo->query("SELECT state FROM payments WHERE id = 'payment-exec-1'")->fetchColumn();
        self::assertSame('refunded', $payment);

        // Re-running the sweep is a no-op (no pending refunds left).
        self::assertSame(['succeeded' => 0, 'failed' => 0], $execution->executePending());
    }

    public function testRefundExecutionFailsWhenPaymentHasNoIntent(): void
    {
        $bookingId = $this->seedConfirmedBooking('booking-exec-2');
        $this->seedPaymentForBooking('payment-exec-2', $bookingId);
        $this->pdo->exec("UPDATE payments SET stripe_payment_intent_id = NULL WHERE id = 'payment-exec-2'");

        $noShowHeaders = $this->actorHeaders(['provider']);
        $noShowHeaders['Idempotency-Key'] = 'idem-exec-noshow-2';
        $this->router->dispatch(new Request('POST', "/api/v1/bookings/$bookingId:mark-provider-no-show", $noShowHeaders, []));

        $refunds = $this->router->dispatch(new Request('GET', "/api/v1/bookings/$bookingId/refunds", $this->actorHeaders(['admin']), []));
        $refundId = $refunds->body['data'][0]['refund_id'];
        $approveHeaders = $this->actorHeaders(['admin']);
        $approveHeaders['Idempotency-Key'] = 'idem-exec-approve-2';
        $this->router->dispatch(new Request('POST', "/api/v1/refunds/$refundId:approve", $approveHeaders, []));

        $execution = new ExecuteRefundService(
            new PdoTransactionManager($this->pdo),
            new PdoRefundRepository($this->pdo),
            new PdoPaymentRepository($this->pdo),
            new StubStripeGateway(),
            new PdoAuditLogger($this->pdo),
        );

        self::assertSame(['succeeded' => 0, 'failed' => 1], $execution->executePending());

        $refund = $this->pdo->query("SELECT state, failure_reason FROM refunds WHERE id = '$refundId'")->fetch(\PDO::FETCH_ASSOC);
        self::assertSame('failed', $refund['state']);
        self::assertStringContainsString('payment intent', (string) $refund['failure_reason']);
    }

    public function testClientCanListOwnBookings(): void
    {
        $headers = $this->actorHeaders(['client']);
        $headers['Idempotency-Key'] = 'idem-booking-list-1';
        $created = $this->router->dispatch(new Request('POST', '/api/v1/bookings', $headers, ['opening_id' => 'opening-1']));
        $bookingId = $created->body['data']['booking_id'];

        $response = $this->router->dispatch(new Request('GET', '/api/v1/me/bookings', $this->actorHeaders(['client']), []));
        self::assertSame(200, $response->statusCode);
        self::assertCount(1, $response->body['data']);
        self::assertSame($bookingId, $response->body['data'][0]['booking_id']);

        $filtered = $this->router->dispatch(new Request('GET', '/api/v1/me/bookings?state=confirmed', $this->actorHeaders(['client']), []));
        self::assertSame(200, $filtered->statusCode);
        self::assertCount(0, $filtered->body['data']);

        $denied = $this->router->dispatch(new Request('GET', '/api/v1/me/bookings', $this->actorHeaders(['provider']), []));
        self::assertSame(403, $denied->statusCode);
    }

    public function testPaymentInitiationCreatesThenReplays(): void
    {
        $headers = $this->actorHeaders(['client']);
        $headers['Idempotency-Key'] = 'idem-booking-2';
        $createBooking = new Request('POST', '/api/v1/bookings', $headers, ['opening_id' => 'opening-2']);
        $booking = $this->router->dispatch($createBooking);

        $paymentHeaders = $this->actorHeaders(['client']);
        $paymentHeaders['Idempotency-Key'] = 'idem-payment-1';
        $paymentPath = '/api/v1/bookings/' . $booking->body['data']['booking_id'] . '/payments/initiate';
        $payload = ['payment_method_type' => 'card', 'return_url' => 'https://example.test/return'];

        $first = $this->router->dispatch(new Request('POST', $paymentPath, $paymentHeaders, $payload));
        self::assertSame(201, $first->statusCode);
        self::assertSame('requires_action', $first->body['data']['gateway_status']['status']);

        $second = $this->router->dispatch(new Request('POST', $paymentPath, $paymentHeaders, $payload));
        self::assertTrue($second->body['meta']['idempotency_replayed']);
        self::assertSame($first->body['data']['payment_id'], $second->body['data']['payment_id']);
    }

    public function testStripeWebhookIsDeduplicatedByStripeEventId(): void
    {
        $event = ['id' => 'evt_123', 'type' => 'payment_intent.succeeded', 'data' => ['object' => ['id' => 'pi_1']]];
        $raw = json_encode($event, JSON_THROW_ON_ERROR);
        $sig = hash_hmac('sha256', $raw, 'test_webhook_secret');

        $first = $this->router->dispatch(new Request('POST', '/api/v1/webhooks/stripe', ['Stripe-Signature' => $sig], [], rawBody: $raw));
        self::assertSame(200, $first->statusCode);

        $second = $this->router->dispatch(new Request('POST', '/api/v1/webhooks/stripe', ['Stripe-Signature' => $sig], [], rawBody: $raw));
        self::assertSame(200, $second->statusCode);
        self::assertTrue($second->body['meta']['deduplicated']);

        $count = (int) $this->pdo->query("SELECT COUNT(*) FROM stripe_webhook_events WHERE stripe_event_id = 'evt_123'")->fetchColumn();
        self::assertSame(1, $count);
    }

    public function testAdminCanCreateOrganization(): void
    {
        $response = $this->router->dispatch(new Request('POST', '/api/v1/admin/organizations', $this->actorHeaders(['admin']), [
            'legal_name' => 'Studio d.o.o.',
            'display_name' => 'Studio Zagreb',
            'tax_id' => 'HR123',
            'contact_email' => 'info@example.com',
            'contact_phone' => '+38591111222',
        ]));

        self::assertSame(201, $response->statusCode);
        self::assertSame('Studio d.o.o.', $response->body['data']['legal_name']);
        self::assertNotEmpty($response->body['data']['organization_id']);
    }

    public function testCreateOrganizationFailsValidationWhenLegalNameMissing(): void
    {
        $response = $this->router->dispatch(new Request('POST', '/api/v1/admin/organizations', $this->actorHeaders(['admin']), [
            'display_name' => 'Studio Zagreb',
            'contact_email' => 'info@example.com',
            'contact_phone' => '+38591111222',
        ]));

        self::assertSame(422, $response->statusCode);
        self::assertSame('VALIDATION_REQUIRED_FIELD_MISSING', $response->body['error']['code']);
    }

    public function testCreateOrganizationReturnsConflictWhenLegalNameAlreadyExists(): void
    {
        $payload = [
            'legal_name' => 'Dup Org d.o.o.',
            'display_name' => 'Dup Org',
            'contact_email' => 'dup-org@example.com',
            'contact_phone' => '+38591119999',
        ];

        $first = $this->router->dispatch(new Request('POST', '/api/v1/admin/organizations', $this->actorHeaders(['admin']), $payload));
        self::assertSame(201, $first->statusCode);

        $second = $this->router->dispatch(new Request('POST', '/api/v1/admin/organizations', $this->actorHeaders(['admin']), $payload));
        self::assertSame(409, $second->statusCode);
        self::assertSame('CONFLICT_ORGANIZATION_LEGAL_NAME_EXISTS', $second->body['error']['code']);
        self::assertArrayNotHasKey('data', $second->body);
    }

    public function testAdminCanCreateProviderForOrganization(): void
    {
        $organization = $this->router->dispatch(new Request('POST', '/api/v1/admin/organizations', $this->actorHeaders(['admin']), [
            'legal_name' => 'Wellness d.o.o.',
            'display_name' => 'Wellness Zagreb',
            'contact_email' => 'hello@example.com',
            'contact_phone' => '+38591112233',
        ]));

        $response = $this->router->dispatch(new Request('POST', '/api/v1/admin/providers', $this->actorHeaders(['admin']), [
            'organization_id' => $organization->body['data']['organization_id'],
            'display_name' => 'Wellness Zagreb - Ana',
            'status' => 'active',
        ]));

        self::assertSame(201, $response->statusCode);
        self::assertSame('organization', $response->body['data']['provider_type']);
        self::assertSame($organization->body['data']['organization_id'], $response->body['data']['organization_id']);
    }

    public function testCreateProviderFailsValidationWhenStatusInvalid(): void
    {
        $organization = $this->router->dispatch(new Request('POST', '/api/v1/admin/organizations', $this->actorHeaders(['admin']), [
            'legal_name' => 'Invalid Status d.o.o.',
            'display_name' => 'Invalid Status',
            'contact_email' => 'status@example.com',
            'contact_phone' => '+38591112234',
        ]));

        $response = $this->router->dispatch(new Request('POST', '/api/v1/admin/providers', $this->actorHeaders(['admin']), [
            'organization_id' => $organization->body['data']['organization_id'],
            'display_name' => 'Provider',
            'status' => 'pending',
        ]));

        self::assertSame(422, $response->statusCode);
        self::assertSame('VALIDATION_PROVIDER_STATUS_INVALID', $response->body['error']['code']);
    }

    public function testCreateProviderFailsWhenOrganizationMissing(): void
    {
        $response = $this->router->dispatch(new Request('POST', '/api/v1/admin/providers', $this->actorHeaders(['admin']), [
            'organization_id' => 'org-missing',
            'display_name' => 'Provider',
            'status' => 'active',
        ]));

        self::assertSame(422, $response->statusCode);
        self::assertSame('VALIDATION_ORGANIZATION_NOT_FOUND', $response->body['error']['code']);
    }

    public function testAdminCanCreateUserForProviderWithMultipleRoles(): void
    {
        $organization = $this->router->dispatch(new Request('POST', '/api/v1/admin/organizations', $this->actorHeaders(['admin']), [
            'legal_name' => 'Users d.o.o.',
            'display_name' => 'Users Zagreb',
            'contact_email' => 'users@example.com',
            'contact_phone' => '+38591112235',
        ]));
        $provider = $this->router->dispatch(new Request('POST', '/api/v1/admin/providers', $this->actorHeaders(['admin']), [
            'organization_id' => $organization->body['data']['organization_id'],
            'display_name' => 'Users Provider',
            'status' => 'active',
        ]));

        $response = $this->router->dispatch(new Request('POST', '/api/v1/admin/users', $this->actorHeaders(['admin']), [
            'first_name' => 'Ana',
            'last_name' => 'Horvat',
            'email' => 'ana@example.com',
            'phone' => '+38591111222',
            'roles' => ['provider_staff', 'provider_manager'],
            'provider_id' => $provider->body['data']['provider_id'],
        ]));

        self::assertSame(201, $response->statusCode);
        self::assertSame(['provider_staff', 'provider_manager'], $response->body['data']['roles']);
        self::assertFalse($response->body['data']['password_set']);
    }

    public function testCreateUserFailsValidationWhenRolesMissing(): void
    {
        $organization = $this->router->dispatch(new Request('POST', '/api/v1/admin/organizations', $this->actorHeaders(['admin']), [
            'legal_name' => 'Roleless d.o.o.',
            'display_name' => 'Roleless',
            'contact_email' => 'roleless@example.com',
            'contact_phone' => '+38591112236',
        ]));
        $provider = $this->router->dispatch(new Request('POST', '/api/v1/admin/providers', $this->actorHeaders(['admin']), [
            'organization_id' => $organization->body['data']['organization_id'],
            'display_name' => 'Roleless Provider',
            'status' => 'active',
        ]));

        $response = $this->router->dispatch(new Request('POST', '/api/v1/admin/users', $this->actorHeaders(['admin']), [
            'first_name' => 'Ana',
            'last_name' => 'Horvat',
            'email' => 'ana-roleless@example.com',
            'phone' => '+38591111222',
            'roles' => [],
            'provider_id' => $provider->body['data']['provider_id'],
        ]));

        self::assertSame(422, $response->statusCode);
        self::assertSame('VALIDATION_ROLES_REQUIRED', $response->body['error']['code']);
    }

    public function testCreateUserFailsWhenProviderMissing(): void
    {
        $response = $this->router->dispatch(new Request('POST', '/api/v1/admin/users', $this->actorHeaders(['admin']), [
            'first_name' => 'Ana',
            'last_name' => 'Horvat',
            'email' => 'ana-missing-provider@example.com',
            'phone' => '+38591111222',
            'roles' => ['provider_staff'],
            'provider_id' => 'provider-missing',
        ]));

        self::assertSame(422, $response->statusCode);
        self::assertSame('VALIDATION_PROVIDER_NOT_FOUND', $response->body['error']['code']);
    }

    public function testCreateUserReturnsConflictWhenEmailAlreadyExists(): void
    {
        $organization = $this->router->dispatch(new Request('POST', '/api/v1/admin/organizations', $this->actorHeaders(['admin']), [
            'legal_name' => 'Dup User d.o.o.',
            'display_name' => 'Dup User',
            'contact_email' => 'dup-user-org@example.com',
            'contact_phone' => '+38591112237',
        ]));
        $provider = $this->router->dispatch(new Request('POST', '/api/v1/admin/providers', $this->actorHeaders(['admin']), [
            'organization_id' => $organization->body['data']['organization_id'],
            'display_name' => 'Dup User Provider',
            'status' => 'active',
        ]));

        $payload = [
            'first_name' => 'Ana',
            'last_name' => 'Horvat',
            'email' => 'dup-user@example.com',
            'phone' => '+38591111222',
            'roles' => ['provider_staff'],
            'provider_id' => $provider->body['data']['provider_id'],
        ];

        $first = $this->router->dispatch(new Request('POST', '/api/v1/admin/users', $this->actorHeaders(['admin']), $payload));
        self::assertSame(201, $first->statusCode);

        $second = $this->router->dispatch(new Request('POST', '/api/v1/admin/users', $this->actorHeaders(['admin']), $payload));
        self::assertSame(409, $second->statusCode);
        self::assertSame('CONFLICT_USER_EMAIL_EXISTS', $second->body['error']['code']);
    }

    public function testGetOrganizationsReturnsCreatedRecords(): void
    {
        $created = $this->router->dispatch(new Request('POST', '/api/v1/admin/organizations', $this->actorHeaders(['admin']), [
            'legal_name' => 'List Org d.o.o.',
            'display_name' => 'List Org',
            'contact_email' => 'list-org@example.com',
            'contact_phone' => '+38591112238',
        ]));

        $list = $this->router->dispatch(new Request('GET', '/api/v1/admin/organizations', $this->actorHeaders(['admin']), []));
        self::assertSame(200, $list->statusCode);

        $organizationIds = array_column($list->body['data'], 'organization_id');
        self::assertContains($created->body['data']['organization_id'], $organizationIds);
    }

    public function testGetOrganizationByIdReturnsRecord(): void
    {
        $created = $this->router->dispatch(new Request('POST', '/api/v1/admin/organizations', $this->actorHeaders(['admin']), [
            'legal_name' => 'Org Detail d.o.o.',
            'display_name' => 'Org Detail',
            'contact_email' => 'org-detail@example.com',
            'contact_phone' => '+38591112244',
        ]));

        $response = $this->router->dispatch(new Request('GET', '/api/v1/admin/organizations/' . $created->body['data']['organization_id'], $this->actorHeaders(['admin']), []));
        self::assertSame(200, $response->statusCode);
        self::assertSame($created->body['data']['organization_id'], $response->body['data']['organization_id']);
        self::assertSame('Org Detail d.o.o.', $response->body['data']['legal_name']);
    }

    public function testGetOrganizationByIdReturnsNotFoundWhenMissing(): void
    {
        $response = $this->router->dispatch(new Request('GET', '/api/v1/admin/organizations/missing-organization-id', $this->actorHeaders(['admin']), []));

        self::assertSame(404, $response->statusCode);
        self::assertSame('ORGANIZATION_NOT_FOUND', $response->body['error']['code']);
    }

    public function testGetProvidersCanFilterByOrganizationId(): void
    {
        $orgA = $this->router->dispatch(new Request('POST', '/api/v1/admin/organizations', $this->actorHeaders(['admin']), [
            'legal_name' => 'Filter Org A d.o.o.',
            'display_name' => 'Filter Org A',
            'contact_email' => 'filter-a@example.com',
            'contact_phone' => '+38591112239',
        ]));
        $orgB = $this->router->dispatch(new Request('POST', '/api/v1/admin/organizations', $this->actorHeaders(['admin']), [
            'legal_name' => 'Filter Org B d.o.o.',
            'display_name' => 'Filter Org B',
            'contact_email' => 'filter-b@example.com',
            'contact_phone' => '+38591112240',
        ]));

        $providerA = $this->router->dispatch(new Request('POST', '/api/v1/admin/providers', $this->actorHeaders(['admin']), [
            'organization_id' => $orgA->body['data']['organization_id'],
            'display_name' => 'Provider A',
            'status' => 'active',
        ]));
        $this->router->dispatch(new Request('POST', '/api/v1/admin/providers', $this->actorHeaders(['admin']), [
            'organization_id' => $orgB->body['data']['organization_id'],
            'display_name' => 'Provider B',
            'status' => 'active',
        ]));

        $list = $this->router->dispatch(new Request('GET', '/api/v1/admin/providers?organization_id=' . $orgA->body['data']['organization_id'], $this->actorHeaders(['admin']), []));
        self::assertSame(200, $list->statusCode);
        self::assertCount(1, $list->body['data']);
        self::assertSame($providerA->body['data']['provider_id'], $list->body['data'][0]['provider_id']);
    }

    public function testGetProviderByIdReturnsRecord(): void
    {
        $organization = $this->router->dispatch(new Request('POST', '/api/v1/admin/organizations', $this->actorHeaders(['admin']), [
            'legal_name' => 'Provider Detail d.o.o.',
            'display_name' => 'Provider Detail',
            'contact_email' => 'provider-detail@example.com',
            'contact_phone' => '+38591112245',
        ]));
        $created = $this->router->dispatch(new Request('POST', '/api/v1/admin/providers', $this->actorHeaders(['admin']), [
            'organization_id' => $organization->body['data']['organization_id'],
            'display_name' => 'Provider Detail One',
            'status' => 'active',
        ]));

        $response = $this->router->dispatch(new Request('GET', '/api/v1/admin/providers/' . $created->body['data']['provider_id'], $this->actorHeaders(['admin']), []));
        self::assertSame(200, $response->statusCode);
        self::assertSame($created->body['data']['provider_id'], $response->body['data']['provider_id']);
        self::assertSame($organization->body['data']['organization_id'], $response->body['data']['organization_id']);
    }

    public function testGetProviderByIdReturnsNotFoundWhenMissing(): void
    {
        $response = $this->router->dispatch(new Request('GET', '/api/v1/admin/providers/missing-provider-id', $this->actorHeaders(['admin']), []));

        self::assertSame(404, $response->statusCode);
        self::assertSame('PROVIDER_NOT_FOUND', $response->body['error']['code']);
    }

    public function testGetUsersCanFilterByProviderId(): void
    {
        $organization = $this->router->dispatch(new Request('POST', '/api/v1/admin/organizations', $this->actorHeaders(['admin']), [
            'legal_name' => 'Filter User d.o.o.',
            'display_name' => 'Filter User',
            'contact_email' => 'filter-user@example.com',
            'contact_phone' => '+38591112241',
        ]));
        $providerA = $this->router->dispatch(new Request('POST', '/api/v1/admin/providers', $this->actorHeaders(['admin']), [
            'organization_id' => $organization->body['data']['organization_id'],
            'display_name' => 'User Provider A',
            'status' => 'active',
        ]));
        $providerB = $this->router->dispatch(new Request('POST', '/api/v1/admin/providers', $this->actorHeaders(['admin']), [
            'organization_id' => $organization->body['data']['organization_id'],
            'display_name' => 'User Provider B',
            'status' => 'active',
        ]));

        $userA = $this->router->dispatch(new Request('POST', '/api/v1/admin/users', $this->actorHeaders(['admin']), [
            'first_name' => 'User',
            'last_name' => 'A',
            'email' => 'filter-user-a@example.com',
            'phone' => '+38591112242',
            'roles' => ['provider_staff'],
            'provider_id' => $providerA->body['data']['provider_id'],
        ]));
        $this->router->dispatch(new Request('POST', '/api/v1/admin/users', $this->actorHeaders(['admin']), [
            'first_name' => 'User',
            'last_name' => 'B',
            'email' => 'filter-user-b@example.com',
            'phone' => '+38591112243',
            'roles' => ['provider_staff'],
            'provider_id' => $providerB->body['data']['provider_id'],
        ]));

        $list = $this->router->dispatch(new Request('GET', '/api/v1/admin/users?provider_id=' . $providerA->body['data']['provider_id'], $this->actorHeaders(['admin']), []));
        self::assertSame(200, $list->statusCode);
        self::assertCount(1, $list->body['data']);
        self::assertSame($userA->body['data']['user_id'], $list->body['data'][0]['user_id']);
    }

    public function testGetUserByIdReturnsRecord(): void
    {
        $organization = $this->router->dispatch(new Request('POST', '/api/v1/admin/organizations', $this->actorHeaders(['admin']), [
            'legal_name' => 'User Detail d.o.o.',
            'display_name' => 'User Detail',
            'contact_email' => 'user-detail@example.com',
            'contact_phone' => '+38591112246',
        ]));
        $provider = $this->router->dispatch(new Request('POST', '/api/v1/admin/providers', $this->actorHeaders(['admin']), [
            'organization_id' => $organization->body['data']['organization_id'],
            'display_name' => 'User Detail Provider',
            'status' => 'active',
        ]));
        $created = $this->router->dispatch(new Request('POST', '/api/v1/admin/users', $this->actorHeaders(['admin']), [
            'first_name' => 'User',
            'last_name' => 'Detail',
            'email' => 'user-detail-one@example.com',
            'phone' => '+38591112247',
            'roles' => ['provider_manager', 'provider_staff'],
            'provider_id' => $provider->body['data']['provider_id'],
        ]));

        $response = $this->router->dispatch(new Request('GET', '/api/v1/admin/users/' . $created->body['data']['user_id'], $this->actorHeaders(['admin']), []));
        self::assertSame(200, $response->statusCode);
        self::assertSame($created->body['data']['user_id'], $response->body['data']['user_id']);
        self::assertSame($provider->body['data']['provider_id'], $response->body['data']['provider_id']);
        self::assertSame(['provider_manager', 'provider_staff'], $response->body['data']['roles']);
        self::assertArrayNotHasKey('password_hash', $response->body['data']);
    }

    public function testGetUserByIdReturnsNotFoundWhenMissing(): void
    {
        $response = $this->router->dispatch(new Request('GET', '/api/v1/admin/users/missing-user-id', $this->actorHeaders(['admin']), []));

        self::assertSame(404, $response->statusCode);
        self::assertSame('USER_NOT_FOUND', $response->body['error']['code']);
    }

    public function testAdminCanCreateApiKeyAndUseItAsBearerToken(): void
    {
        $create = $this->router->dispatch(new Request('POST', '/api/v1/api-key', $this->actorHeaders(['admin']), [
            'name' => 'Client mobile app',
        ]));

        self::assertSame(201, $create->statusCode);
        self::assertArrayHasKey('api_key', $create->body['data']);
        self::assertArrayHasKey('api_key_id', $create->body['data']);
        self::assertSame('Client mobile app', $create->body['data']['name']);

        $me = $this->router->dispatch(new Request('GET', '/api/v1/me', [
            'Authorization' => 'Bearer ' . $create->body['data']['api_key'],
        ], []));

        self::assertSame(200, $me->statusCode);
        self::assertSame('actor-1', $me->body['data']['actor_id']);
        self::assertSame(['admin'], $me->body['data']['roles']);
    }

    public function testAdminBearerTokenCanCallProtectedAdminEndpoint(): void
    {
        $created = $this->apiKeys->createForActor('admin', 'local-dev-admin', ['admin'], 'FE admin token', 'lm_dev_admin_fixture_1234567890');

        $response = $this->router->dispatch(new Request('GET', '/api/v1/admin/organizations', [
            'Authorization' => 'Bearer lm_dev_admin_fixture_1234567890',
        ], []));

        self::assertSame(200, $response->statusCode);
        self::assertSame([], $response->body['data']);
        self::assertSame('admin', $created['actor_type']);
    }

    public function testCreateApiKeyFailsValidationWhenNameIsEmpty(): void
    {
        $response = $this->router->dispatch(new Request('POST', '/api/v1/api-key', $this->actorHeaders(['admin']), [
            'name' => '',
        ]));

        self::assertSame(422, $response->statusCode);
        self::assertSame('VALIDATION_REQUIRED_FIELD_MISSING', $response->body['error']['code']);
    }

    public function testGetApiKeysReturnsMetadataOnlyWithoutRawToken(): void
    {
        $create = $this->router->dispatch(new Request('POST', '/api/v1/api-key', $this->actorHeaders(['admin']), [
            'name' => 'List metadata key',
        ]));
        self::assertSame(201, $create->statusCode);

        $list = $this->router->dispatch(new Request('GET', '/api/v1/api-keys', $this->actorHeaders(['admin']), []));
        self::assertSame(200, $list->statusCode);
        self::assertGreaterThanOrEqual(1, count($list->body['data']));
        $item = $list->body['data'][0];
        self::assertArrayHasKey('api_key_id', $item);
        self::assertArrayHasKey('name', $item);
        self::assertArrayHasKey('key_prefix', $item);
        self::assertArrayHasKey('created_by', $item);
        self::assertSame('actor-1', $item['created_by']);
        self::assertArrayHasKey('created_at', $item);
        self::assertArrayHasKey('revoked_at', $item);
        self::assertArrayHasKey('is_active', $item);
        self::assertArrayNotHasKey('api_key', $item);
    }

    public function testAdminCanRevokeApiKeyByApiKeyId(): void
    {
        $create = $this->router->dispatch(new Request('POST', '/api/v1/api-key', $this->actorHeaders(['admin']), [
            'name' => 'Temporary key',
        ]));
        $plainKey = $create->body['data']['api_key'];
        $apiKeyId = $create->body['data']['api_key_id'];

        $delete = $this->router->dispatch(new Request('DELETE', '/api/v1/api-key/' . $apiKeyId, $this->actorHeaders(['admin']), []));
        self::assertSame(200, $delete->statusCode);
        self::assertTrue($delete->body['data']['revoked']);
        self::assertSame($apiKeyId, $delete->body['data']['api_key_id']);

        $meAfterDelete = $this->router->dispatch(new Request('GET', '/api/v1/me', [
            'Authorization' => 'Bearer ' . $plainKey,
        ], []));
        self::assertSame(401, $meAfterDelete->statusCode);
    }

    private function actorHeaders(array $roles): array
    {
        return [
            'X-Actor-Id' => 'actor-1',
            'X-Actor-Subject' => 'sso|user_1',
            'X-Actor-Roles' => implode(',', $roles),
            'X-User-Profile-Id' => 'profile-client-1',
        ];
    }

    private function seedPaymentForBooking(string $paymentId, string $bookingId, string $state = 'captured'): string
    {
        $now = (new \DateTimeImmutable('2026-03-26T10:06:00Z'))->format(DATE_ATOM);
        $this->pdo->exec("INSERT INTO payments (id, booking_id, provider_id, client_user_profile_id, state, amount, currency, stripe_payment_intent_id, created_at, updated_at) VALUES ('$paymentId', '$bookingId', 'provider-1', 'profile-client-1', '$state', 2200, 'EUR', 'pi_$paymentId', '$now', '$now')");

        return $paymentId;
    }

    private function seedConfirmedBooking(string $bookingId): string
    {
        $now = (new \DateTimeImmutable('2026-03-26T10:05:00Z'))->format(DATE_ATOM);
        $this->pdo->exec("INSERT INTO bookings (id, opening_id, provider_id, client_user_profile_id, state, reservation_expires_at, payment_required_amount, payment_currency, confirmed_at, created_at, updated_at) VALUES ('$bookingId', 'opening-1', 'provider-1', 'profile-client-1', 'confirmed', NULL, 2200, 'EUR', '$now', '$now', '$now')");

        return $bookingId;
    }

    private function seedFixtureData(): void
    {
        $now = new \DateTimeImmutable('2026-03-26T10:00:00Z');
        $later = $now->modify('+30 minutes');
        $laterTwo = $later->modify('+30 minutes');
        $laterThree = $laterTwo->modify('+30 minutes');
        $nowIso = $now->format(DATE_ATOM);
        $laterIso = $later->format(DATE_ATOM);
        $laterTwoIso = $laterTwo->format(DATE_ATOM);
        $laterThreeIso = $laterThree->format(DATE_ATOM);

        $this->pdo->exec("INSERT INTO user_profiles (id, identity_subject, status, created_at, updated_at) VALUES ('profile-client-1', 'sso|user_1', 'active', '$nowIso', '$nowIso')");
        $this->pdo->exec("INSERT INTO providers (id, provider_type, owner_user_profile_id, organization_id, status, created_at, updated_at) VALUES ('provider-1', 'individual', 'profile-client-1', NULL, 'active', '$nowIso', '$nowIso')");
        $this->pdo->exec("INSERT INTO service_offerings (id, provider_id, name, description, duration_minutes, price_amount, price_currency, status, created_at, updated_at) VALUES ('offering-1', 'provider-1', 'Haircut', NULL, 30, 2200, 'EUR', 'active', '$nowIso', '$nowIso')");
        $this->pdo->exec("INSERT INTO openings (id, provider_id, service_offering_id, starts_at, ends_at, timezone, capacity, status, published_at, cancelled_at, created_at, updated_at, price_amount, price_currency) VALUES ('opening-1', 'provider-1', 'offering-1', '$laterIso', '$laterTwoIso', 'UTC', 1, 'published', '$nowIso', NULL, '$nowIso', '$nowIso', 2200, 'EUR')");
        $this->pdo->exec("INSERT INTO openings (id, provider_id, service_offering_id, starts_at, ends_at, timezone, capacity, status, published_at, cancelled_at, created_at, updated_at, price_amount, price_currency) VALUES ('opening-2', 'provider-1', 'offering-1', '$laterTwoIso', '$laterThreeIso', 'UTC', 1, 'published', '$nowIso', NULL, '$nowIso', '$nowIso', 2200, 'EUR')");
    }
}

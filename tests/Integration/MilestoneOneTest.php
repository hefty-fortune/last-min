<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Bootstrap\Routing\ApiV1Routes;
use App\Bootstrap\Routing\Router;
use App\Common\Http\Request;
use App\Common\Security\ActorContextResolver;
use App\Modules\AdminSetup\Api\OrganizationAdminController;
use App\Modules\AdminSetup\Api\ProviderAdminController;
use App\Modules\AdminSetup\Api\UserAdminController;
use App\Modules\AdminSetup\Application\Service\CreateOrganizationService;
use App\Modules\AdminSetup\Application\Service\CreateProviderService as CreateAdminProviderService;
use App\Modules\AdminSetup\Application\Service\CreateUserService;
use App\Modules\AdminSetup\Infrastructure\Persistence\PdoAdminProviderRepository;
use App\Modules\AdminSetup\Infrastructure\Persistence\PdoOrganizationRepository;
use App\Modules\AdminSetup\Infrastructure\Persistence\PdoUserRepository;
use App\Modules\Booking\Api\BookingController;
use App\Modules\Booking\Application\Service\CreateBookingService;
use App\Modules\Booking\Infrastructure\Persistence\PdoBookingRepository;
use App\Modules\IdentityAccess\Api\MeController;
use App\Modules\IdentityAccess\Application\Query\GetMeQueryService;
use App\Modules\Openings\Api\OpeningController;
use App\Modules\Openings\Application\Service\CreateOpeningService;
use App\Modules\Openings\Infrastructure\Persistence\PdoOpeningRepository;
use App\Modules\Payments\Api\PaymentController;
use App\Modules\Payments\Application\Service\InitiatePaymentService;
use App\Modules\Payments\Infrastructure\Persistence\PdoPaymentRepository;
use App\Modules\Providers\Api\ProviderController;
use App\Modules\Providers\Application\Service\CreateProviderService;
use App\Modules\Providers\Infrastructure\Persistence\PdoProviderRepository;
use App\Platform\Idempotency\IdempotencyExecutor;
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

    protected function setUp(): void
    {
        $this->pdo = new PDO('sqlite::memory:');
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $schema = file_get_contents(__DIR__ . '/../../migrations/20260326_000001_milestone1.sql');
        self::assertNotFalse($schema);
        $this->pdo->exec($schema);

        $this->seedFixtureData();

        $idempotency = new IdempotencyExecutor(new PdoIdempotencyStore($this->pdo));
        $this->router = new Router();

        (new ApiV1Routes(new ActorContextResolver()))->register(
            $this->router,
            new OrganizationAdminController(new CreateOrganizationService(new PdoOrganizationRepository($this->pdo))),
            new ProviderAdminController(new CreateAdminProviderService(new PdoOrganizationRepository($this->pdo), new PdoAdminProviderRepository($this->pdo))),
            new UserAdminController(new CreateUserService(new PdoAdminProviderRepository($this->pdo), new PdoUserRepository($this->pdo))),
            new MeController(new GetMeQueryService()),
            new ProviderController(new CreateProviderService(new PdoProviderRepository($this->pdo)), $idempotency),
            new OpeningController(new CreateOpeningService(new PdoOpeningRepository($this->pdo)), $idempotency),
            new BookingController(new CreateBookingService(new PdoTransactionManager($this->pdo), new PdoOpeningRepository($this->pdo), new PdoBookingRepository($this->pdo)), $idempotency),
            new PaymentController(new InitiatePaymentService(new PdoBookingRepository($this->pdo), new PdoPaymentRepository($this->pdo), new StubStripeGateway()), $idempotency),
            new StripeWebhookController(new StripeSignatureVerifier('test_webhook_secret'), new PdoStripeWebhookEventRepository($this->pdo), new StripeWebhookDispatcher()),
        );
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

    private function actorHeaders(array $roles): array
    {
        return [
            'X-Actor-Id' => 'actor-1',
            'X-Actor-Subject' => 'sso|user_1',
            'X-Actor-Roles' => implode(',', $roles),
            'X-User-Profile-Id' => 'profile-client-1',
        ];
    }

    private function seedFixtureData(): void
    {
        $now = (new \DateTimeImmutable())->format(DATE_ATOM);
        $this->pdo->exec("INSERT INTO user_profiles (id, identity_subject, status, created_at, updated_at) VALUES ('profile-client-1', 'sso|user_1', 'active', '$now', '$now')");
        $this->pdo->exec("INSERT INTO providers (id, provider_type, owner_user_profile_id, organization_id, status, created_at, updated_at) VALUES ('provider-1', 'individual', 'profile-client-1', NULL, 'active', '$now', '$now')");
        $this->pdo->exec("INSERT INTO service_offerings (id, provider_id, name, description, duration_minutes, price_amount, price_currency, status, created_at, updated_at) VALUES ('offering-1', 'provider-1', 'Haircut', NULL, 30, 2200, 'EUR', 'active', '$now', '$now')");
        $this->pdo->exec("INSERT INTO openings (id, provider_id, service_offering_id, starts_at, ends_at, timezone, capacity, status, published_at, created_at, updated_at, price_amount, price_currency) VALUES ('opening-1', 'provider-1', 'offering-1', '$now', '$now', 'UTC', 1, 'published', '$now', '$now', '$now', 2200, 'EUR')");
        $this->pdo->exec("INSERT INTO openings (id, provider_id, service_offering_id, starts_at, ends_at, timezone, capacity, status, published_at, created_at, updated_at, price_amount, price_currency) VALUES ('opening-2', 'provider-1', 'offering-1', '$now', '$now', 'UTC', 1, 'published', '$now', '$now', '$now', 2200, 'EUR')");
    }
}

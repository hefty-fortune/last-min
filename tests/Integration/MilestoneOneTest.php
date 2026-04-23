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
use App\Modules\AdminSetup\Application\Service\GetOrganizationService;
use App\Modules\AdminSetup\Application\Service\GetProviderService;
use App\Modules\AdminSetup\Application\Service\GetUserService;
use App\Modules\AdminSetup\Application\Service\ListOrganizationsService;
use App\Modules\AdminSetup\Application\Service\ListProvidersService;
use App\Modules\AdminSetup\Application\Service\ListUsersService;
use App\Modules\AdminSetup\Infrastructure\Persistence\PdoAdminProviderRepository;
use App\Modules\AdminSetup\Infrastructure\Persistence\PdoOrganizationRepository;
use App\Modules\AdminSetup\Infrastructure\Persistence\PdoUserRepository;
use App\Modules\Booking\Api\BookingController;
use App\Modules\Booking\Application\Service\CreateBookingService;
use App\Modules\Booking\Infrastructure\Persistence\PdoBookingRepository;
use App\Modules\IdentityAccess\Api\ApiKeyController;
use App\Modules\IdentityAccess\Api\MeController;
use App\Modules\IdentityAccess\Application\Query\GetMeQueryService;
use App\Modules\IdentityAccess\Application\Service\CreateApiKeyService;
use App\Modules\IdentityAccess\Application\Service\DeleteApiKeyService;
use App\Modules\IdentityAccess\Application\Service\ListApiKeysService;
use App\Modules\IdentityAccess\Infrastructure\Persistence\PdoApiKeyRepository;
use App\Modules\IdentityAccess\Infrastructure\Security\ApiKeyBearerTokenActorResolver;
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
    private PdoApiKeyRepository $apiKeys;

    protected function setUp(): void
    {
        $this->pdo = new PDO('sqlite::memory:');
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $schema = file_get_contents(__DIR__ . '/../../migrations/20260326_000001_milestone1.sql');
        self::assertNotFalse($schema);
        $this->pdo->exec($schema);

        $this->seedFixtureData();

        $idempotency = new IdempotencyExecutor(new PdoIdempotencyStore($this->pdo));
        $apiKeys = new PdoApiKeyRepository($this->pdo);
        $this->apiKeys = $apiKeys;
        $this->router = new Router();

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
                new ListUsersService(new PdoUserRepository($this->pdo))
            ),
            new ApiKeyController(new CreateApiKeyService($apiKeys), new DeleteApiKeyService($apiKeys), new ListApiKeysService($apiKeys)),
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
        $clientId = '8f726eaf-bc9a-4010-a8b8-4ee3f39b7c10';
        $create = $this->router->dispatch(new Request('POST', '/api/v1/api-key', $this->actorHeaders(['admin']), [
            'client_id' => $clientId,
            'name' => 'Client mobile app',
        ]));

        self::assertSame(201, $create->statusCode);
        self::assertSame($clientId, $create->body['data']['client_id']);
        self::assertArrayHasKey('api_key', $create->body['data']);
        self::assertArrayHasKey('api_key_id', $create->body['data']);

        $me = $this->router->dispatch(new Request('GET', '/api/v1/me', [
            'Authorization' => 'Bearer ' . $create->body['data']['api_key'],
        ], []));

        self::assertSame(200, $me->statusCode);
        self::assertSame($clientId, $me->body['data']['actor_id']);
        self::assertSame(['client'], $me->body['data']['roles']);
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

    public function testCreateApiKeyFailsValidationWhenClientIdIsInvalid(): void
    {
        $response = $this->router->dispatch(new Request('POST', '/api/v1/api-key', $this->actorHeaders(['admin']), [
            'client_id' => 'not-a-uuid',
            'name' => 'Bad key',
        ]));

        self::assertSame(422, $response->statusCode);
        self::assertSame('VALIDATION_CLIENT_ID_INVALID', $response->body['error']['code']);
    }

    public function testGetApiKeysReturnsMetadataOnlyWithoutRawToken(): void
    {
        $clientId = '2deb6808-3f17-4e98-a5fc-867b44db630f';
        $create = $this->router->dispatch(new Request('POST', '/api/v1/api-key', $this->actorHeaders(['admin']), [
            'client_id' => $clientId,
            'name' => 'List metadata key',
        ]));
        self::assertSame(201, $create->statusCode);

        $list = $this->router->dispatch(new Request('GET', '/api/v1/api-keys?client_id=' . $clientId, $this->actorHeaders(['admin']), []));
        self::assertSame(200, $list->statusCode);
        self::assertCount(1, $list->body['data']);
        self::assertArrayHasKey('api_key_id', $list->body['data'][0]);
        self::assertArrayHasKey('name', $list->body['data'][0]);
        self::assertArrayHasKey('client_id', $list->body['data'][0]);
        self::assertArrayHasKey('created_at', $list->body['data'][0]);
        self::assertArrayHasKey('revoked_at', $list->body['data'][0]);
        self::assertArrayHasKey('is_active', $list->body['data'][0]);
        self::assertArrayNotHasKey('api_key', $list->body['data'][0]);
    }

    public function testAdminCanRevokeApiKeyByApiKeyId(): void
    {
        $clientId = '6e869011-faa2-4df8-89f5-0a7f131fcf01';
        $create = $this->router->dispatch(new Request('POST', '/api/v1/api-key', $this->actorHeaders(['admin']), [
            'client_id' => $clientId,
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

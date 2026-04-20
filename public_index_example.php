<?php

declare(strict_types=1);

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
use App\Modules\IdentityAccess\Api\ApiKeyController;
use App\Modules\IdentityAccess\Api\MeController;
use App\Modules\IdentityAccess\Application\Service\CreateApiKeyService;
use App\Modules\IdentityAccess\Application\Service\DeleteApiKeyService;
use App\Modules\IdentityAccess\Application\Query\GetMeQueryService;
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

require __DIR__ . '/vendor/autoload.php';

$pdo = new PDO('sqlite::memory:');
$router = new Router();
$idempotency = new IdempotencyExecutor(new PdoIdempotencyStore($pdo));

$apiKeys = new PdoApiKeyRepository($pdo);
$routes = new ApiV1Routes(new ActorContextResolver(new ApiKeyBearerTokenActorResolver($apiKeys)));
$routes->register(
    $router,
    new OrganizationAdminController(new CreateOrganizationService(new PdoOrganizationRepository($pdo))),
    new ProviderAdminController(new CreateAdminProviderService(new PdoOrganizationRepository($pdo), new PdoAdminProviderRepository($pdo))),
    new UserAdminController(new CreateUserService(new PdoAdminProviderRepository($pdo), new PdoUserRepository($pdo))),
    new ApiKeyController(new CreateApiKeyService($apiKeys), new DeleteApiKeyService($apiKeys)),
    new MeController(new GetMeQueryService()),
    new ProviderController(new CreateProviderService(new PdoProviderRepository($pdo)), $idempotency),
    new OpeningController(new CreateOpeningService(new PdoOpeningRepository($pdo)), $idempotency),
    new BookingController(new CreateBookingService(new PdoTransactionManager($pdo), new PdoOpeningRepository($pdo), new PdoBookingRepository($pdo)), $idempotency),
    new PaymentController(new InitiatePaymentService(new PdoBookingRepository($pdo), new PdoPaymentRepository($pdo), new StubStripeGateway()), $idempotency),
    new StripeWebhookController(new StripeSignatureVerifier('stripe-webhook-secret'), new PdoStripeWebhookEventRepository($pdo), new StripeWebhookDispatcher()),
);

$request = new Request('GET', '/api/v1/me', getallheaders(), []);
$response = $router->dispatch($request);

http_response_code($response->statusCode);
header('Content-Type: application/json');
echo json_encode($response->body, JSON_THROW_ON_ERROR);

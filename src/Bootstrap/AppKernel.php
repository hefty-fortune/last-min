<?php

declare(strict_types=1);

namespace App\Bootstrap;

use App\Bootstrap\Routing\ApiV1Routes;
use App\Bootstrap\Routing\Router;
use App\Modules\AdminOps\Api\AdminOpsController;
use App\Modules\AdminOps\Application\Service\AdminOpsQueryService;
use App\Modules\AdminOps\Application\Service\ForceExpireOpeningService;
use App\Modules\AdminOps\Infrastructure\Persistence\PdoAdminOpsReadRepository;
use App\Common\Security\ActorContextResolver;
use App\Common\Security\ApiKeyGateMiddleware;
use App\Common\Security\CompositeBearerTokenActorResolver;
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
use App\Modules\IdentityAccess\Infrastructure\Security\SessionBearerTokenActorResolver;
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
use App\Modules\Payments\Infrastructure\Persistence\PdoPaymentRepository;
use App\Modules\Providers\Api\ProviderController;
use App\Modules\Providers\Application\Service\CreateProviderService;
use App\Modules\Providers\Application\Service\GetProviderProfileService;
use App\Modules\Providers\Application\Service\LinkProviderService;
use App\Modules\Providers\Application\Service\UpdateProviderService;
use App\Modules\Providers\Infrastructure\Persistence\PdoProviderRepository;
use App\Modules\Refunds\Api\RefundController;
use App\Modules\Refunds\Application\Service\ApproveRefundService;
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
use App\Platform\Idempotency\PdoIdempotencyStore;
use App\Platform\Outbox\PdoOutboxMessageStore;
use App\Platform\Integrations\Stripe\StubStripeGateway;
use App\Platform\Persistence\PdoTransactionManager;
use App\Platform\Webhooks\Stripe\PdoStripeWebhookEventRepository;
use App\Platform\Webhooks\Stripe\StripeSignatureVerifier;
use App\Platform\Webhooks\Stripe\StripeWebhookController;
use App\Platform\Webhooks\Stripe\StripeWebhookDispatcher;
use PDO;

final class AppKernel
{
    public static function buildRouter(PDO $pdo, string $stripeWebhookSecret): Router
    {
        $router = new Router();
        $idempotency = new IdempotencyExecutor(new PdoIdempotencyStore($pdo));
        $apiKeys = new PdoApiKeyRepository($pdo);
        $providerRepository = new PdoProviderRepository($pdo);
        $openingRepository = new PdoOpeningRepository($pdo);
        $tx = new PdoTransactionManager($pdo);
        $openingAccess = new OpeningAccessService($providerRepository);
        $sessions = new PdoAuthSessionRepository($pdo);
        $userAuth = new PdoUserAuthRepository($pdo);
        $audit = new PdoAuditLogger($pdo);
        $outbox = new PdoOutboxMessageStore($pdo);

        $bearerResolver = new CompositeBearerTokenActorResolver([
            new SessionBearerTokenActorResolver($sessions, $pdo),
            new ApiKeyBearerTokenActorResolver($apiKeys),
        ]);
        $apiKeyGate = new ApiKeyGateMiddleware($apiKeys);

        (new ApiV1Routes(new ActorContextResolver($bearerResolver, $apiKeyGate), $apiKeyGate))->register(
            $router,
            new OrganizationAdminController(
                new CreateOrganizationService(new PdoOrganizationRepository($pdo)),
                new GetOrganizationService(new PdoOrganizationRepository($pdo)),
                new ListOrganizationsService(new PdoOrganizationRepository($pdo))
            ),
            new ProviderAdminController(
                new CreateAdminProviderService(new PdoOrganizationRepository($pdo), new PdoAdminProviderRepository($pdo)),
                new GetProviderService(new PdoAdminProviderRepository($pdo)),
                new ListProvidersService(new PdoAdminProviderRepository($pdo))
            ),
            new UserAdminController(
                new CreateUserService(new PdoAdminProviderRepository($pdo), new PdoUserRepository($pdo)),
                new GetUserService(new PdoUserRepository($pdo)),
                new ListUsersService(new PdoUserRepository($pdo)),
                new UpdateUserService(new PdoUserRepository($pdo)),
                new UpdateUserRolesService(new PdoUserRepository($pdo)),
                new ResetUserPasswordService(new PdoUserRepository($pdo))
            ),
            new ApiKeyController(new CreateApiKeyService($apiKeys), new DeleteApiKeyService($apiKeys), new ListApiKeysService($apiKeys)),
            new AuthController(new LoginService($userAuth, $sessions)),
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
                new CreateBookingService($tx, $openingRepository, new PdoBookingRepository($pdo)),
                new GetBookingService(new PdoBookingRepository($pdo), new PdoPaymentRepository($pdo), $providerRepository),
                new ListMyBookingsService(new PdoBookingRepository($pdo)),
                new MarkNoShowService(
                    $tx,
                    new PdoBookingRepository($pdo),
                    $providerRepository,
                    new RequestRefundService(new PdoPaymentRepository($pdo), new PdoRefundRepository($pdo)),
                    $audit,
                    $outbox
                ),
                $idempotency
            ),
            new PaymentController(
                new InitiatePaymentService(new PdoBookingRepository($pdo), new PdoPaymentRepository($pdo), new StubStripeGateway()),
                new GetPaymentService(new PdoPaymentRepository($pdo), $providerRepository),
                $idempotency
            ),
            new RefundController(
                new ListBookingRefundsService(new PdoRefundRepository($pdo), new PdoBookingRepository($pdo), $providerRepository),
                new ApproveRefundService(new PdoRefundRepository($pdo), $audit, $outbox),
                $idempotency
            ),
            new OfferingController(
                new CreateOfferingService(new PdoOfferingRepository($pdo), new OfferingAccessService($providerRepository)),
                new ListOfferingsService(new PdoOfferingRepository($pdo), new OfferingAccessService($providerRepository)),
                new UpdateOfferingService(new PdoOfferingRepository($pdo), new OfferingAccessService($providerRepository)),
                $idempotency
            ),
            new OrganizationController(
                new CreateOrganizationSelfService($tx, new PdoOrganizationRepository($pdo), new PdoOrganizationMemberRepository($pdo)),
                new ViewOrganizationService(new PdoOrganizationRepository($pdo), new PdoOrganizationMemberRepository($pdo)),
                new AddOrganizationMemberService(new PdoOrganizationRepository($pdo), new PdoOrganizationMemberRepository($pdo)),
                $idempotency
            ),
            new AdminOpsController(
                new AdminOpsQueryService(new PdoAdminOpsReadRepository($pdo)),
                new ForceExpireOpeningService($tx, $openingRepository, $audit),
                $idempotency
            ),
            new StripeWebhookController(new StripeSignatureVerifier($stripeWebhookSecret), new PdoStripeWebhookEventRepository($pdo), new StripeWebhookDispatcher()),
        );

        return $router;
    }
}

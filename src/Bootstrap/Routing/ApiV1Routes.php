<?php

declare(strict_types=1);

namespace App\Bootstrap\Routing;

use App\Common\Http\Request;
use App\Common\Security\ActorContextResolver;
use App\Modules\AdminSetup\Api\OrganizationAdminController;
use App\Modules\AdminSetup\Api\ProviderAdminController;
use App\Modules\AdminSetup\Api\UserAdminController;
use App\Modules\Booking\Api\BookingController;
use App\Modules\IdentityAccess\Api\MeController;
use App\Modules\Openings\Api\OpeningController;
use App\Modules\Payments\Api\PaymentController;
use App\Modules\Providers\Api\ProviderController;
use App\Platform\Webhooks\Stripe\StripeWebhookController;

final class ApiV1Routes
{
    public function __construct(private ActorContextResolver $resolver)
    {
    }

    public function register(
        Router $router,
        OrganizationAdminController $adminOrganizations,
        ProviderAdminController $adminProviders,
        UserAdminController $adminUsers,
        MeController $me,
        ProviderController $providers,
        OpeningController $openings,
        BookingController $bookings,
        PaymentController $payments,
        StripeWebhookController $stripeWebhook,
    ): void {
        $router->add('POST', '/api/v1/admin/organizations', function (Request $request) use ($adminOrganizations) {
            $actor = $this->resolver->resolve($request->headers);
            return $adminOrganizations->create($actor, $request);
        });

        $router->add('POST', '/api/v1/admin/providers', function (Request $request) use ($adminProviders) {
            $actor = $this->resolver->resolve($request->headers);
            return $adminProviders->create($actor, $request);
        });

        $router->add('POST', '/api/v1/admin/users', function (Request $request) use ($adminUsers) {
            $actor = $this->resolver->resolve($request->headers);
            return $adminUsers->create($actor, $request);
        });

        $router->add('GET', '/api/v1/me', function (Request $request) use ($me) {
            $actor = $this->resolver->resolve($request->headers);
            return $me->get($actor);
        });

        $router->add('POST', '/api/v1/providers', function (Request $request) use ($providers) {
            $actor = $this->resolver->resolve($request->headers);
            return $providers->create($actor, $request);
        });

        $router->add('POST', '/api/v1/providers/{provider_id}/openings', function (Request $request, array $params) use ($openings) {
            $actor = $this->resolver->resolve($request->headers);
            return $openings->create($actor, $request, $params['provider_id']);
        });

        $router->add('POST', '/api/v1/bookings', function (Request $request) use ($bookings) {
            $actor = $this->resolver->resolve($request->headers);
            return $bookings->create($actor, $request);
        });

        $router->add('POST', '/api/v1/bookings/{booking_id}/payments/initiate', function (Request $request, array $params) use ($payments) {
            $actor = $this->resolver->resolve($request->headers);
            return $payments->initiate($actor, $request, $params['booking_id']);
        });

        $router->add('POST', '/api/v1/webhooks/stripe', fn (Request $request) => $stripeWebhook->ingest($request));
    }
}

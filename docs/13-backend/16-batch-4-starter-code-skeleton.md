# Batch 4 — Starter Code Skeleton (Canonical)

This batch provides a framework-agnostic PHP 8+ starter skeleton aligned with:

- `docs/13-backend/00-locked-foundation-summary.md`
- `docs/13-backend/13-batch-1-architecture-foundation.md`
- `docs/13-backend/14-batch-2-domain-and-schema.md`
- `docs/13-backend/15-batch-3-api-contracts.md`

It intentionally stays skeletal while being realistic enough for incremental implementation by a small team.

---

## 1) Proposed `src/` file structure (representative)

```text
src/
├── Bootstrap/
│   ├── ModuleRegistry/ModuleRegistry.php
│   └── Routing/ApiV1Routes.php
├── Common/
│   ├── Api/
│   │   ├── ApiError.php
│   │   └── ApiResponse.php
│   ├── Domain/
│   │   ├── DomainEvent.php
│   │   └── Money.php
│   └── Security/
│       └── ActorContext.php
├── Modules/
│   ├── IdentityAccess/
│   │   ├── Api/MeController.php
│   │   ├── Application/
│   │   │   ├── Dto/MeResponse.php
│   │   │   ├── Guard/AuthorizationService.php
│   │   │   ├── Port/ActorRoleAssignmentRepository.php
│   │   │   └── Query/GetMeQueryService.php
│   │   └── Infrastructure/Persistence/PdoActorRoleAssignmentRepository.php
│   ├── UserProfiles/
│   │   ├── Api/ProfileController.php
│   │   ├── Application/
│   │   │   ├── Dto/UpdateProfileRequest.php
│   │   │   ├── Dto/UserProfileResponse.php
│   │   │   ├── Port/UserProfileRepository.php
│   │   │   └── Service/UpdateProfileService.php
│   │   └── Infrastructure/Persistence/PdoUserProfileRepository.php
│   ├── Providers/
│   │   ├── Api/ProviderController.php
│   │   ├── Application/
│   │   │   ├── Dto/CreateProviderRequest.php
│   │   │   ├── Dto/ProviderResponse.php
│   │   │   ├── Port/ProviderRepository.php
│   │   │   └── Service/CreateProviderService.php
│   │   └── Infrastructure/Persistence/PdoProviderRepository.php
│   ├── Organizations/
│   │   ├── Api/OrganizationController.php
│   │   ├── Application/
│   │   │   ├── Dto/AddOrganizationMemberRequest.php
│   │   │   ├── Port/OrganizationRepository.php
│   │   │   └── Service/AddOrganizationMemberService.php
│   │   └── Infrastructure/Persistence/PdoOrganizationRepository.php
│   ├── ServiceCatalog/
│   │   ├── Api/ServiceOfferingController.php
│   │   ├── Application/
│   │   │   ├── Dto/CreateServiceOfferingRequest.php
│   │   │   ├── Dto/ServiceOfferingResponse.php
│   │   │   ├── Port/ServiceOfferingRepository.php
│   │   │   └── Service/CreateServiceOfferingService.php
│   │   └── Infrastructure/Persistence/PdoServiceOfferingRepository.php
│   ├── Openings/
│   │   ├── Api/OpeningController.php
│   │   ├── Application/
│   │   │   ├── Dto/CreateOpeningRequest.php
│   │   │   ├── Dto/OpeningResponse.php
│   │   │   ├── Port/OpeningRepository.php
│   │   │   └── Service/CreateOpeningService.php
│   │   └── Infrastructure/Persistence/PdoOpeningRepository.php
│   ├── Booking/
│   │   ├── Api/BookingController.php
│   │   ├── Application/
│   │   │   ├── Command/CreateBookingCommand.php
│   │   │   ├── Dto/BookingResponse.php
│   │   │   ├── Guard/BookingPolicy.php
│   │   │   ├── Port/BookingRepository.php
│   │   │   ├── Port/OpeningAvailabilityGateway.php
│   │   │   └── Service/CreateBookingService.php
│   │   ├── Domain/
│   │   │   ├── Booking.php
│   │   │   ├── BookingEvent.php
│   │   │   └── BookingState.php
│   │   └── Infrastructure/Persistence/PdoBookingRepository.php
│   ├── Payments/
│   │   ├── Api/PaymentController.php
│   │   ├── Application/
│   │   │   ├── Command/InitiatePaymentCommand.php
│   │   │   ├── Dto/PaymentResponse.php
│   │   │   ├── Port/PaymentGateway.php
│   │   │   ├── Port/PaymentRepository.php
│   │   │   └── Service/InitiatePaymentService.php
│   │   ├── Domain/
│   │   │   ├── Payment.php
│   │   │   ├── PaymentEvent.php
│   │   │   └── PaymentState.php
│   │   └── Infrastructure/Persistence/PdoPaymentRepository.php
│   ├── Refunds/
│   │   ├── Api/RefundController.php
│   │   ├── Application/
│   │   │   ├── Dto/RefundResponse.php
│   │   │   ├── Port/RefundRepository.php
│   │   │   └── Service/ApproveRefundService.php
│   │   ├── Domain/
│   │   │   ├── Refund.php
│   │   │   └── RefundState.php
│   │   └── Infrastructure/Persistence/PdoRefundRepository.php
│   ├── Admin/
│   │   ├── Api/AdminController.php
│   │   └── Application/Service/ForceExpireOpeningService.php
│   └── AuditLogging/
│       ├── Application/Port/AuditLogWriter.php
│       └── Infrastructure/Persistence/PdoAuditLogWriter.php
└── Platform/
    ├── Idempotency/
    │   ├── IdempotencyStore.php
    │   └── SqlIdempotencyStore.php
    ├── Outbox/
    │   ├── OutboxMessage.php
    │   └── OutboxPublisher.php
    ├── Persistence/
    │   ├── ConnectionProvider.php
    │   └── TransactionManager.php
    ├── Runtime/
    │   └── Jobs/
    │       ├── JobHandler.php
    │       └── SendNotificationJob.php
    ├── Webhooks/
    │   └── Stripe/
    │       ├── StripeWebhookController.php
    │       ├── StripeWebhookDispatcher.php
    │       ├── StripeWebhookEventDeduplicator.php
    │       └── Handlers/
    │           ├── PaymentIntentSucceededHandler.php
    │           └── PaymentIntentFailedHandler.php
    └── Locking/
        └── AdvisoryLockManager.php
```

---

## 2) Shared foundation (cross-module)

### `src/Common/Security/ActorContext.php`

```php
<?php

declare(strict_types=1);

namespace App\Common\Security;

final readonly class ActorContext
{
    /** @param list<string> $roles */
    public function __construct(
        public string $actorId,
        public string $upstreamSubject,
        public array $roles,
        public ?string $userProfileId,
    ) {
    }

    public function hasRole(string $role): bool
    {
        return in_array($role, $this->roles, true);
    }
}
```

### `src/Common/Api/ApiError.php`

```php
<?php

declare(strict_types=1);

namespace App\Common\Api;

final readonly class ApiError
{
    /** @param list<array{field?: string, issue?: string}> $details */
    public function __construct(
        public string $code,
        public string $message,
        public array $details = [],
        public bool $retryable = false,
        public ?string $requestId = null,
        public ?string $idempotencyKey = null,
    ) {
    }

    public function toArray(): array
    {
        return [
            'error' => [
                'code' => $this->code,
                'message' => $this->message,
                'details' => $this->details,
                'retryable' => $this->retryable,
                'request_id' => $this->requestId,
                'idempotency_key' => $this->idempotencyKey,
            ],
        ];
    }
}
```

### `src/Platform/Persistence/TransactionManager.php`

```php
<?php

declare(strict_types=1);

namespace App\Platform\Persistence;

interface TransactionManager
{
    /**
     * @template T
     * @param callable(): T $callback
     * @return T
     */
    public function withinTransaction(callable $callback): mixed;
}
```

---

## 3) IdentityAccess module

### `src/Modules/IdentityAccess/Application/Guard/AuthorizationService.php`

```php
<?php

declare(strict_types=1);

namespace App\Modules\IdentityAccess\Application\Guard;

use App\Common\Security\ActorContext;

final class AuthorizationService
{
    /** @param list<string> $allowedRoles */
    public function assertAnyRole(ActorContext $actor, array $allowedRoles): void
    {
        foreach ($allowedRoles as $role) {
            if ($actor->hasRole($role)) {
                return;
            }
        }

        // TODO: map to standardized FORBIDDEN_* error in API adapter.
        throw new \RuntimeException('FORBIDDEN_ROLE_MISMATCH');
    }
}
```

### `src/Modules/IdentityAccess/Api/MeController.php`

```php
<?php

declare(strict_types=1);

namespace App\Modules\IdentityAccess\Api;

use App\Common\Api\ApiResponse;
use App\Common\Security\ActorContext;
use App\Modules\IdentityAccess\Application\Query\GetMeQueryService;

final class MeController
{
    public function __construct(private GetMeQueryService $queryService)
    {
    }

    public function get(ActorContext $actor): ApiResponse
    {
        return ApiResponse::ok(['data' => $this->queryService->get($actor)->toArray()]);
    }
}
```

---

## 4) UserProfiles module

### `src/Modules/UserProfiles/Application/Dto/UpdateProfileRequest.php`

```php
<?php

declare(strict_types=1);

namespace App\Modules\UserProfiles\Application\Dto;

final readonly class UpdateProfileRequest
{
    public function __construct(
        public string $displayName,
        public ?string $locale,
        public ?string $timezone,
    ) {
    }
}
```

### `src/Modules/UserProfiles/Application/Service/UpdateProfileService.php`

```php
<?php

declare(strict_types=1);

namespace App\Modules\UserProfiles\Application\Service;

use App\Common\Security\ActorContext;
use App\Modules\UserProfiles\Application\Dto\UpdateProfileRequest;
use App\Modules\UserProfiles\Application\Port\UserProfileRepository;

final class UpdateProfileService
{
    public function __construct(private UserProfileRepository $profiles)
    {
    }

    public function updateMe(ActorContext $actor, UpdateProfileRequest $request): void
    {
        if ($actor->userProfileId === null) {
            throw new \RuntimeException('AUTH_PROFILE_NOT_LINKED');
        }

        // TODO: domain validation rules.
        $this->profiles->updateProfile(
            profileId: $actor->userProfileId,
            displayName: $request->displayName,
            locale: $request->locale,
            timezone: $request->timezone,
        );
    }
}
```

---

## 5) Providers + Organizations modules

### `src/Modules/Providers/Application/Dto/CreateProviderRequest.php`

```php
<?php

declare(strict_types=1);

namespace App\Modules\Providers\Application\Dto;

final readonly class CreateProviderRequest
{
    public function __construct(
        public string $providerType, // individual|organization
        public string $displayName,
        public ?string $organizationId,
        public string $idempotencyKey,
    ) {
    }
}
```

### `src/Modules/Providers/Application/Service/CreateProviderService.php`

```php
<?php

declare(strict_types=1);

namespace App\Modules\Providers\Application\Service;

use App\Common\Security\ActorContext;
use App\Modules\IdentityAccess\Application\Guard\AuthorizationService;
use App\Modules\Providers\Application\Dto\CreateProviderRequest;
use App\Modules\Providers\Application\Port\ProviderRepository;
use App\Platform\Idempotency\IdempotencyStore;

final class CreateProviderService
{
    public function __construct(
        private AuthorizationService $authorization,
        private IdempotencyStore $idempotency,
        private ProviderRepository $providers,
    ) {
    }

    public function create(ActorContext $actor, CreateProviderRequest $request): string
    {
        $this->authorization->assertAnyRole($actor, ['provider']);

        $scope = 'POST:/api/v1/providers';
        if ($this->idempotency->hasReplay($scope, $request->idempotencyKey)) {
            return $this->idempotency->getReplayResourceId($scope, $request->idempotencyKey);
        }

        // TODO: enforce organization ownership/membership checks for provider_type=organization.
        $providerId = $this->providers->create(
            providerType: $request->providerType,
            displayName: $request->displayName,
            ownerUserProfileId: $actor->userProfileId,
            organizationId: $request->organizationId,
        );

        $this->idempotency->storeReplayResourceId($scope, $request->idempotencyKey, $providerId);
        return $providerId;
    }
}
```

### `src/Modules/Organizations/Application/Service/AddOrganizationMemberService.php`

```php
<?php

declare(strict_types=1);

namespace App\Modules\Organizations\Application\Service;

use App\Common\Security\ActorContext;
use App\Modules\IdentityAccess\Application\Guard\AuthorizationService;
use App\Modules\Organizations\Application\Port\OrganizationRepository;

final class AddOrganizationMemberService
{
    public function __construct(
        private AuthorizationService $authorization,
        private OrganizationRepository $organizations,
    ) {
    }

    public function addMember(
        ActorContext $actor,
        string $organizationId,
        string $memberProfileId,
        string $organizationRole // owner|manager|staff
    ): void {
        $this->authorization->assertAnyRole($actor, ['provider', 'admin', 'super_admin']);

        // TODO: validate actor is org owner/manager or admin/super_admin per policy.
        $this->organizations->addMember($organizationId, $memberProfileId, $organizationRole);
    }
}
```

---

## 6) ServiceCatalog + Openings modules

### `src/Modules/ServiceCatalog/Application/Dto/CreateServiceOfferingRequest.php`

```php
<?php

declare(strict_types=1);

namespace App\Modules\ServiceCatalog\Application\Dto;

final readonly class CreateServiceOfferingRequest
{
    public function __construct(
        public string $providerId,
        public string $name,
        public int $durationMinutes,
        public int $amountMinor,
        public string $currency,
        public string $idempotencyKey,
    ) {
    }
}
```

### `src/Modules/Openings/Application/Dto/CreateOpeningRequest.php`

```php
<?php

declare(strict_types=1);

namespace App\Modules\Openings\Application\Dto;

final readonly class CreateOpeningRequest
{
    public function __construct(
        public string $providerId,
        public string $serviceOfferingId,
        public \DateTimeImmutable $startsAt,
        public \DateTimeImmutable $endsAt,
        public int $priceAmountMinor,
        public string $priceCurrency,
        public string $idempotencyKey,
    ) {
    }
}
```

### `src/Modules/Openings/Application/Dto/OpeningResponse.php`

```php
<?php

declare(strict_types=1);

namespace App\Modules\Openings\Application\Dto;

final readonly class OpeningResponse
{
    public function __construct(
        public string $openingId,
        public string $providerId,
        public string $serviceOfferingId,
        public string $startsAt,
        public string $endsAt,
        public string $status, // draft|published|reserved|booked|expired|cancelled_by_provider
    ) {
    }
}
```

---

## 7) Booking module (critical write path)

### `src/Modules/Booking/Domain/BookingState.php`

```php
<?php

declare(strict_types=1);

namespace App\Modules\Booking\Domain;

enum BookingState: string
{
    case initiated = 'initiated';
    case reserved = 'reserved';
    case payment_pending = 'payment_pending';
    case confirmed = 'confirmed';
    case completed = 'completed';
    case client_no_show = 'client_no_show';
    case provider_no_show = 'provider_no_show';
    case cancelled_by_provider = 'cancelled_by_provider';
    case reservation_expired = 'reservation_expired';
    case payment_failed = 'payment_failed';
    case refunded = 'refunded';
}
```

### `src/Modules/Booking/Application/Command/CreateBookingCommand.php`

```php
<?php

declare(strict_types=1);

namespace App\Modules\Booking\Application\Command;

final readonly class CreateBookingCommand
{
    public function __construct(
        public string $openingId,
        public string $clientUserProfileId,
        public string $idempotencyKey,
        public \DateTimeImmutable $requestedAt,
    ) {
    }
}
```

### `src/Modules/Booking/Application/Guard/BookingPolicy.php`

```php
<?php

declare(strict_types=1);

namespace App\Modules\Booking\Application\Guard;

use App\Common\Security\ActorContext;

final class BookingPolicy
{
    public function assertClientCanCreateBooking(ActorContext $actor): void
    {
        if (!$actor->hasRole('client')) {
            throw new \RuntimeException('FORBIDDEN_ROLE_MISMATCH');
        }

        if ($actor->userProfileId === null) {
            throw new \RuntimeException('AUTH_PROFILE_NOT_LINKED');
        }
    }
}
```

### `src/Modules/Booking/Application/Service/CreateBookingService.php`

```php
<?php

declare(strict_types=1);

namespace App\Modules\Booking\Application\Service;

use App\Modules\Booking\Application\Command\CreateBookingCommand;
use App\Modules\Booking\Application\Guard\BookingPolicy;
use App\Modules\Booking\Application\Port\BookingRepository;
use App\Modules\Booking\Application\Port\OpeningAvailabilityGateway;
use App\Modules\Booking\Domain\Booking;
use App\Modules\Booking\Domain\BookingEvent;
use App\Modules\Booking\Domain\BookingState;
use App\Platform\Idempotency\IdempotencyStore;
use App\Platform\Outbox\OutboxPublisher;
use App\Platform\Persistence\TransactionManager;

final class CreateBookingService
{
    public function __construct(
        private BookingPolicy $policy,
        private IdempotencyStore $idempotency,
        private TransactionManager $tx,
        private OpeningAvailabilityGateway $openingAvailability,
        private BookingRepository $bookings,
        private OutboxPublisher $outbox,
    ) {
    }

    public function handle(CreateBookingCommand $command): Booking
    {
        // Policy check occurs in controller before this call in this skeleton.

        $scope = 'POST:/api/v1/bookings';
        if ($this->idempotency->hasReplay($scope, $command->idempotencyKey)) {
            return $this->bookings->getById(
                $this->idempotency->getReplayResourceId($scope, $command->idempotencyKey)
            );
        }

        return $this->tx->withinTransaction(function () use ($command, $scope): Booking {
            // Canonical Batch 2 strategy: row lock opening before reserve decision (SELECT ... FOR UPDATE).
            $this->openingAvailability->lockOpeningForReservation($command->openingId);
            // Optional extension: add advisory locking in addition to row locking for defense-in-depth.

            if (!$this->openingAvailability->isReservable($command->openingId, $command->requestedAt)) {
                throw new \RuntimeException('BOOKING_OPENING_ALREADY_RESERVED');
            }

            $booking = Booking::reserve(
                openingId: $command->openingId,
                clientUserProfileId: $command->clientUserProfileId,
                reservedAt: $command->requestedAt,
                expiresAt: $command->requestedAt->modify('+10 minutes'),
                state: BookingState::reserved,
            );

            $this->bookings->save($booking);

            $this->outbox->publish(BookingEvent::reserved($booking));
            $this->idempotency->storeReplayResourceId($scope, $command->idempotencyKey, $booking->bookingId);

            return $booking;
        });
    }
}
```

### `src/Modules/Booking/Api/BookingController.php`

```php
<?php

declare(strict_types=1);

namespace App\Modules\Booking\Api;

use App\Common\Api\ApiResponse;
use App\Common\Security\ActorContext;
use App\Modules\Booking\Application\Command\CreateBookingCommand;
use App\Modules\Booking\Application\Guard\BookingPolicy;
use App\Modules\Booking\Application\Service\CreateBookingService;

final class BookingController
{
    public function __construct(
        private BookingPolicy $policy,
        private CreateBookingService $createBooking,
    ) {
    }

    public function create(ActorContext $actor, array $payload, string $idempotencyKey): ApiResponse
    {
        $this->policy->assertClientCanCreateBooking($actor);

        $command = new CreateBookingCommand(
            openingId: (string) $payload['opening_id'],
            clientUserProfileId: (string) $actor->userProfileId,
            idempotencyKey: $idempotencyKey,
            requestedAt: new \DateTimeImmutable('now'),
        );

        $booking = $this->createBooking->handle($command);

        return ApiResponse::created([
            'data' => [
                'booking_id' => $booking->bookingId,
                'opening_id' => $booking->openingId,
                'client_user_profile_id' => $booking->clientUserProfileId,
                'state' => $booking->state->value,
                'reserved_at' => $booking->reservedAt->format(DATE_ATOM),
                'expires_at' => $booking->expiresAt->format(DATE_ATOM),
            ],
        ]);
    }
}
```

---

## 8) Payments module

### `src/Modules/Payments/Domain/PaymentState.php`

```php
<?php

declare(strict_types=1);

namespace App\Modules\Payments\Domain;

enum PaymentState: string
{
    case initiated = 'initiated';
    case authorized = 'authorized';
    case captured = 'captured';
    case failed = 'failed';
    case refunded = 'refunded';
}
```

### `src/Modules/Payments/Application/Command/InitiatePaymentCommand.php`

```php
<?php

declare(strict_types=1);

namespace App\Modules\Payments\Application\Command;

final readonly class InitiatePaymentCommand
{
    public function __construct(
        public string $bookingId,
        public string $clientUserProfileId,
        public string $idempotencyKey,
    ) {
    }
}
```

### `src/Modules/Payments/Application/Port/PaymentGateway.php`

```php
<?php

declare(strict_types=1);

namespace App\Modules\Payments\Application\Port;

use App\Modules\Payments\Domain\Payment;

interface PaymentGateway
{
    /**
     * TODO: return type can be promoted to dedicated VO when implemented.
     *
     * @return array{provider: string, external_id: string, status: string}
     */
    public function createPaymentIntent(Payment $payment): array;
}
```

### `src/Modules/Payments/Application/Service/InitiatePaymentService.php`

```php
<?php

declare(strict_types=1);

namespace App\Modules\Payments\Application\Service;

use App\Modules\Booking\Application\Port\BookingRepository;
use App\Modules\Booking\Domain\BookingState;
use App\Modules\Payments\Application\Command\InitiatePaymentCommand;
use App\Modules\Payments\Application\Port\PaymentGateway;
use App\Modules\Payments\Application\Port\PaymentRepository;
use App\Modules\Payments\Domain\Payment;
use App\Modules\Payments\Domain\PaymentEvent;
use App\Modules\Payments\Domain\PaymentState;
use App\Platform\Idempotency\IdempotencyStore;
use App\Platform\Outbox\OutboxPublisher;
use App\Platform\Persistence\TransactionManager;

final class InitiatePaymentService
{
    public function __construct(
        private IdempotencyStore $idempotency,
        private TransactionManager $tx,
        private BookingRepository $bookings,
        private PaymentRepository $payments,
        private PaymentGateway $gateway,
        private OutboxPublisher $outbox,
    ) {
    }

    public function handle(InitiatePaymentCommand $command): Payment
    {
        $scope = 'POST:/api/v1/bookings/{booking_id}/payments/initiate';
        if ($this->idempotency->hasReplay($scope, $command->idempotencyKey)) {
            return $this->payments->getById(
                $this->idempotency->getReplayResourceId($scope, $command->idempotencyKey)
            );
        }

        return $this->tx->withinTransaction(function () use ($command, $scope): Payment {
            $booking = $this->bookings->getById($command->bookingId);
            if ($booking->clientUserProfileId !== $command->clientUserProfileId) {
                throw new \RuntimeException('FORBIDDEN_BOOKING_ACCESS_DENIED');
            }
            if ($booking->state !== BookingState::reserved && $booking->state !== BookingState::payment_pending) {
                throw new \RuntimeException('PAYMENT_BOOKING_NOT_PAYABLE');
            }

            $payment = Payment::initiateForBooking($booking);
            $gatewayReference = $this->gateway->createPaymentIntent($payment);

            $payment->attachGatewayReference(
                provider: $gatewayReference['provider'],
                externalId: $gatewayReference['external_id'],
                gatewayStatus: $gatewayReference['status'],
            );

            $this->payments->save($payment);
            $this->outbox->publish(PaymentEvent::initiated($payment));
            $this->idempotency->storeReplayResourceId($scope, $command->idempotencyKey, $payment->paymentId);

            return $payment;
        });
    }
}
```

---

## 9) Refunds + Admin modules

### `src/Modules/Refunds/Domain/RefundState.php`

```php
<?php

declare(strict_types=1);

namespace App\Modules\Refunds\Domain;

enum RefundState: string
{
    case requested = 'requested';
    case pending = 'pending';
    case succeeded = 'succeeded';
    case failed = 'failed';
    case cancelled = 'cancelled';
}
```

### `src/Modules/Refunds/Application/Service/ApproveRefundService.php`

```php
<?php

declare(strict_types=1);

namespace App\Modules\Refunds\Application\Service;

use App\Common\Security\ActorContext;
use App\Modules\IdentityAccess\Application\Guard\AuthorizationService;
use App\Modules\Refunds\Application\Port\RefundRepository;
use App\Modules\Refunds\Domain\RefundState;
use App\Platform\Persistence\TransactionManager;

final class ApproveRefundService
{
    public function __construct(
        private AuthorizationService $authorization,
        private TransactionManager $tx,
        private RefundRepository $refunds,
    ) {
    }

    public function approve(ActorContext $actor, string $refundId): void
    {
        $this->authorization->assertAnyRole($actor, ['admin', 'super_admin']);

        $this->tx->withinTransaction(function () use ($refundId): void {
            $refund = $this->refunds->getById($refundId);
            if ($refund->state !== RefundState::requested) {
                throw new \RuntimeException('REFUND_INVALID_STATE_TRANSITION');
            }

            // TODO: invoke gateway adapter and transition requested -> pending/succeeded.
            $refund->markPending();
            $this->refunds->save($refund);
        });
    }
}
```

### `src/Modules/Admin/Application/Service/ForceExpireOpeningService.php`

```php
<?php

declare(strict_types=1);

namespace App\Modules\Admin\Application\Service;

use App\Common\Security\ActorContext;
use App\Modules\IdentityAccess\Application\Guard\AuthorizationService;
use App\Modules\Openings\Application\Port\OpeningRepository;

final class ForceExpireOpeningService
{
    public function __construct(
        private AuthorizationService $authorization,
        private OpeningRepository $openings,
    ) {
    }

    public function forceExpire(ActorContext $actor, string $openingId): void
    {
        $this->authorization->assertAnyRole($actor, ['super_admin']);
        $this->openings->forceExpire($openingId);
    }
}
```

---

## 10) Platform: webhooks, jobs, outbox, idempotency

### `src/Platform/Webhooks/Stripe/StripeWebhookController.php`

```php
<?php

declare(strict_types=1);

namespace App\Platform\Webhooks\Stripe;

use App\Common\Api\ApiResponse;

final class StripeWebhookController
{
    public function __construct(
        private StripeWebhookEventDeduplicator $deduplicator,
        private StripeWebhookDispatcher $dispatcher,
    ) {
    }

    public function ingest(string $rawPayload, string $signatureHeader): ApiResponse
    {
        // TODO: verify signature against Stripe endpoint secret.
        $event = $this->dispatcher->parse($rawPayload, $signatureHeader);

        if ($this->deduplicator->alreadyProcessed($event['id'])) {
            // Batch 3 contract: return 200 for deduplicated/ignored duplicate events.
            return ApiResponse::ok(['status' => 'duplicate_ignored', 'event_id' => $event['id']]);
        }

        $this->dispatcher->dispatch($event);
        $this->deduplicator->markProcessed($event['id'], $event['type'], new \DateTimeImmutable('now'));

        // Batch 3 contract: return 200 after successful ingestion/dispatch.
        return ApiResponse::ok(['status' => 'processed', 'event_id' => $event['id']]);
    }
}
```

### `src/Platform/Webhooks/Stripe/Handlers/PaymentIntentSucceededHandler.php`

```php
<?php

declare(strict_types=1);

namespace App\Platform\Webhooks\Stripe\Handlers;

use App\Modules\Payments\Application\Port\PaymentRepository;
use App\Modules\Payments\Domain\PaymentState;

final class PaymentIntentSucceededHandler
{
    public function __construct(private PaymentRepository $payments)
    {
    }

    /** @param array<string, mixed> $event */
    public function handle(array $event): void
    {
        $paymentIntentId = (string) ($event['data']['object']['id'] ?? '');
        if ($paymentIntentId === '') {
            throw new \RuntimeException('WEBHOOK_INVALID_PAYLOAD');
        }

        $payment = $this->payments->getByStripePaymentIntentId($paymentIntentId);
        $payment->transitionTo(PaymentState::captured);

        // TODO: emit outbox event for downstream booking confirmation.
        $this->payments->save($payment);
    }
}
```

### `src/Platform/Runtime/Jobs/SendNotificationJob.php`

```php
<?php

declare(strict_types=1);

namespace App\Platform\Runtime\Jobs;

final readonly class SendNotificationJob
{
    public function __construct(
        public string $notificationId,
        public string $channel,
        public \DateTimeImmutable $scheduledAt,
    ) {
    }
}
```

### `src/Platform/Outbox/OutboxPublisher.php`

```php
<?php

declare(strict_types=1);

namespace App\Platform\Outbox;

use App\Common\Domain\DomainEvent;

interface OutboxPublisher
{
    public function publish(DomainEvent $event): void;
}
```

### `src/Platform/Idempotency/IdempotencyStore.php`

```php
<?php

declare(strict_types=1);

namespace App\Platform\Idempotency;

interface IdempotencyStore
{
    public function hasReplay(string $scope, string $idempotencyKey): bool;

    public function getReplayResourceId(string $scope, string $idempotencyKey): string;

    public function storeReplayResourceId(string $scope, string $idempotencyKey, string $resourceId): void;
}
```

---

## 11) Repository interface + infrastructure stubs

### `src/Modules/Booking/Application/Port/BookingRepository.php`

```php
<?php

declare(strict_types=1);

namespace App\Modules\Booking\Application\Port;

use App\Modules\Booking\Domain\Booking;

interface BookingRepository
{
    public function save(Booking $booking): void;

    public function getById(string $bookingId): Booking;
}
```

### `src/Modules/Booking/Infrastructure/Persistence/PdoBookingRepository.php`

```php
<?php

declare(strict_types=1);

namespace App\Modules\Booking\Infrastructure\Persistence;

use App\Modules\Booking\Application\Port\BookingRepository;
use App\Modules\Booking\Domain\Booking;
use PDO;

final class PdoBookingRepository implements BookingRepository
{
    public function __construct(private PDO $pdo)
    {
    }

    public function save(Booking $booking): void
    {
        // TODO: implement INSERT/UPDATE mapping for bookings table.
    }

    public function getById(string $bookingId): Booking
    {
        // TODO: hydrate aggregate from bookings row.
        throw new \RuntimeException('Not implemented');
    }
}
```

---

## 12) API route registration skeleton (Batch 3 names)

### `src/Bootstrap/Routing/ApiV1Routes.php`

```php
<?php

declare(strict_types=1);

namespace App\Bootstrap\Routing;

use App\Modules\Admin\Api\AdminController;
use App\Modules\Booking\Api\BookingController;
use App\Modules\IdentityAccess\Api\MeController;
use App\Modules\Openings\Api\OpeningController;
use App\Modules\Payments\Api\PaymentController;
use App\Modules\Providers\Api\ProviderController;
use App\Platform\Webhooks\Stripe\StripeWebhookController;

final class ApiV1Routes
{
    public function map(RouteCollector $routes): void
    {
        // Identity / profile
        $routes->get('/api/v1/me', [MeController::class, 'get']);

        // Providers
        $routes->post('/api/v1/providers', [ProviderController::class, 'create']);

        // Openings
        $routes->post('/api/v1/providers/{provider_id}/openings', [OpeningController::class, 'create']);

        // Booking
        $routes->post('/api/v1/bookings', [BookingController::class, 'create']);
        $routes->post('/api/v1/bookings/{booking_id}:mark-provider-no-show', [BookingController::class, 'markProviderNoShow']);
        $routes->post('/api/v1/bookings/{booking_id}:mark-client-no-show', [BookingController::class, 'markClientNoShow']);

        // Payments
        $routes->post('/api/v1/bookings/{booking_id}/payments/initiate', [PaymentController::class, 'initiate']);

        // Admin
        $routes->post('/api/v1/admin/openings/{opening_id}:force-expire', [AdminController::class, 'forceExpireOpening']);

        // Stripe webhooks
        $routes->post('/api/v1/webhooks/stripe', [StripeWebhookController::class, 'ingest']);
    }
}
```

---

## 13) Example migration skeletons / SQL drafts

> These are intentionally partial drafts showing ownership, constraints, and idempotency/webhook/outbox support.

### `migrations/20260326_001_create_bookings.sql`

```sql
CREATE TABLE bookings (
    id UUID PRIMARY KEY,
    opening_id UUID NOT NULL,
    provider_id UUID NOT NULL,
    client_user_profile_id UUID NOT NULL,
    state VARCHAR(64) NOT NULL,
    reserved_at TIMESTAMPTZ NOT NULL,
    reservation_expires_at TIMESTAMPTZ NOT NULL,
    payment_required_amount BIGINT NOT NULL,
    payment_currency CHAR(3) NOT NULL,
    confirmed_at TIMESTAMPTZ NULL,
    completed_at TIMESTAMPTZ NULL,
    cancelled_by_provider_at TIMESTAMPTZ NULL,
    no_show_actor VARCHAR(32) NULL,
    no_show_recorded_at TIMESTAMPTZ NULL,
    created_at TIMESTAMPTZ NOT NULL,
    updated_at TIMESTAMPTZ NOT NULL,
    CONSTRAINT bookings_state_chk CHECK (
        state IN (
            'initiated','reserved','payment_pending','confirmed','completed',
            'client_no_show','provider_no_show','cancelled_by_provider',
            'reservation_expired','payment_failed','refunded'
        )
    )
);

CREATE INDEX idx_bookings_opening_id ON bookings (opening_id);
CREATE INDEX idx_bookings_client_state ON bookings (client_user_profile_id, state);
```

### `migrations/20260326_002_create_payments.sql`

```sql
CREATE TABLE payments (
    id UUID PRIMARY KEY,
    booking_id UUID NOT NULL UNIQUE,
    provider_id UUID NOT NULL,
    client_user_profile_id UUID NOT NULL,
    state VARCHAR(32) NOT NULL,
    amount BIGINT NOT NULL,
    currency CHAR(3) NOT NULL,
    stripe_payment_intent_id VARCHAR(255) NULL,
    failed_reason TEXT NULL,
    authorized_at TIMESTAMPTZ NULL,
    captured_at TIMESTAMPTZ NULL,
    refunded_at TIMESTAMPTZ NULL,
    created_at TIMESTAMPTZ NOT NULL,
    updated_at TIMESTAMPTZ NOT NULL,
    -- Additive extensions (do not replace canonical core fields above):
    provider_amount_minor BIGINT NULL,
    platform_fee_minor BIGINT NULL,
    payment_method_type VARCHAR(64) NULL,
    stripe_charge_id VARCHAR(255) NULL,
    gateway_status VARCHAR(64) NULL,
    failed_reason_code VARCHAR(128) NULL,
    failed_reason_message TEXT NULL,
    CONSTRAINT payments_state_chk CHECK (state IN ('initiated','authorized','captured','failed','refunded'))
);

CREATE UNIQUE INDEX uq_payments_stripe_pi ON payments (stripe_payment_intent_id) WHERE stripe_payment_intent_id IS NOT NULL;
CREATE INDEX idx_payments_booking_state ON payments (booking_id, state);
```

### `migrations/20260326_003_create_stripe_webhook_events.sql`

```sql
CREATE TABLE stripe_webhook_events (
    id UUID PRIMARY KEY,
    stripe_event_id VARCHAR(255) NOT NULL,
    event_type VARCHAR(128) NOT NULL,
    payload JSONB NOT NULL,
    signature_valid BOOLEAN NOT NULL,
    processing_state VARCHAR(32) NOT NULL,
    first_received_at TIMESTAMPTZ NOT NULL,
    last_received_at TIMESTAMPTZ NOT NULL,
    processed_at TIMESTAMPTZ NULL,
    failure_reason TEXT NULL
);

CREATE UNIQUE INDEX uq_stripe_webhook_event_id ON stripe_webhook_events (stripe_event_id);
```

### `migrations/20260326_004_create_idempotency_keys.sql`

```sql
CREATE TABLE idempotency_keys (
    id UUID PRIMARY KEY,
    scope VARCHAR(255) NOT NULL,
    idempotency_key VARCHAR(255) NOT NULL,
    request_hash VARCHAR(128) NOT NULL,
    resource_id UUID NULL,
    status_code INT NULL,
    response_body_json JSONB NULL,
    created_at TIMESTAMPTZ NOT NULL,
    expires_at TIMESTAMPTZ NOT NULL
);

CREATE UNIQUE INDEX uq_idempotency_scope_key ON idempotency_keys (scope, idempotency_key);
```

### `migrations/20260326_005_create_outbox_messages.sql`

```sql
CREATE TABLE outbox_messages (
    id UUID PRIMARY KEY,
    topic VARCHAR(255) NOT NULL,
    payload_json JSONB NOT NULL,
    occurred_at TIMESTAMPTZ NOT NULL,
    published_at TIMESTAMPTZ NULL,
    retry_count INT NOT NULL DEFAULT 0,
    last_error TEXT NULL
);

CREATE INDEX idx_outbox_unpublished ON outbox_messages (published_at) WHERE published_at IS NULL;
```

---

## 14) Placeholder suggestions for omitted files (concise)

To avoid dumping placeholders, add only when the team starts implementing each flow:

- `Modules/Notifications/Application/Service/EnqueueNotificationService.php`
- `Modules/Payouts/Application/Service/SchedulePayoutService.php`
- `Modules/AuditLogging/Application/Service/AppendAuditLogService.php`
- `Platform/Runtime/Jobs/ProcessOutboxJob.php`
- `Platform/Webhooks/Stripe/Handlers/ChargeRefundedHandler.php`

---

## 15) Implementation notes (where critical concerns belong)

1. **Locking**: use canonical row locking (`SELECT ... FOR UPDATE`) on the opening row in booking create flow before reservability checks; advisory locking can be added as optional defense-in-depth, not as a replacement.
2. **Idempotency**: enforce `Idempotency-Key` for all critical write endpoints from Batch 3.
3. **Outbox**: publish domain events inside same transaction as aggregate writes.
4. **Webhook dedup**: persist each webhook in `stripe_webhook_events` and deduplicate on unique `stripe_event_id` before dispatch.
5. **Authorization boundary**: consume upstream identity as `ActorContext`, enforce role/policy locally.
6. **No fake completeness**: keep TODOs explicit and avoid pretending adapters are production-final.

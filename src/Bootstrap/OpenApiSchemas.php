<?php

declare(strict_types=1);

namespace App\Bootstrap;

use OpenApi\Attributes as OA;

// -- Common --

#[OA\Schema(
    schema: 'Meta',
    properties: [
        new OA\Property(property: 'request_id', type: 'string'),
    ],
)]

#[OA\Schema(
    schema: 'ErrorResponse',
    properties: [
        new OA\Property(property: 'error', properties: [
            new OA\Property(property: 'code', type: 'string'),
            new OA\Property(property: 'message', type: 'string'),
            new OA\Property(property: 'details', type: 'array', items: new OA\Items(type: 'object')),
            new OA\Property(property: 'retryable', type: 'boolean'),
            new OA\Property(property: 'request_id', type: 'string', nullable: true),
            new OA\Property(property: 'idempotency_key', type: 'string', nullable: true),
        ], type: 'object'),
    ],
)]

// -- Auth --

#[OA\Schema(
    schema: 'LoginResponse',
    properties: [
        new OA\Property(property: 'data', properties: [
            new OA\Property(property: 'token', type: 'string'),
            new OA\Property(property: 'expires_at', type: 'string', format: 'date-time'),
            new OA\Property(property: 'user', properties: [
                new OA\Property(property: 'user_id', type: 'string'),
                new OA\Property(property: 'email', type: 'string'),
                new OA\Property(property: 'first_name', type: 'string'),
                new OA\Property(property: 'last_name', type: 'string'),
                new OA\Property(property: 'roles', type: 'array', items: new OA\Items(type: 'string')),
            ], type: 'object'),
        ], type: 'object'),
        new OA\Property(property: 'meta', ref: '#/components/schemas/Meta'),
    ],
)]

#[OA\Schema(
    schema: 'MeResponse',
    properties: [
        new OA\Property(property: 'data', properties: [
            new OA\Property(property: 'actor_id', type: 'string'),
            new OA\Property(property: 'upstream_subject', type: 'string'),
            new OA\Property(property: 'roles', type: 'array', items: new OA\Items(type: 'string')),
            new OA\Property(property: 'default_role', type: 'string', nullable: true),
            new OA\Property(property: 'profile_id', type: 'string', nullable: true),
        ], type: 'object'),
        new OA\Property(property: 'meta', ref: '#/components/schemas/Meta'),
    ],
)]

// -- API Keys --

#[OA\Schema(
    schema: 'ApiKeyCreatedResponse',
    properties: [
        new OA\Property(property: 'data', properties: [
            new OA\Property(property: 'client_id', type: 'string'),
            new OA\Property(property: 'api_key_id', type: 'string'),
            new OA\Property(property: 'name', type: 'string'),
            new OA\Property(property: 'api_key', type: 'string', description: 'Shown once'),
        ], type: 'object'),
        new OA\Property(property: 'meta', ref: '#/components/schemas/Meta'),
    ],
)]

#[OA\Schema(
    schema: 'ApiKeyItem',
    properties: [
        new OA\Property(property: 'api_key_id', type: 'string'),
        new OA\Property(property: 'client_id', type: 'string'),
        new OA\Property(property: 'name', type: 'string'),
        new OA\Property(property: 'created_at', type: 'string', format: 'date-time'),
        new OA\Property(property: 'revoked_at', type: 'string', format: 'date-time', nullable: true),
        new OA\Property(property: 'is_active', type: 'boolean'),
    ],
)]

#[OA\Schema(
    schema: 'ApiKeyListResponse',
    properties: [
        new OA\Property(property: 'data', type: 'array', items: new OA\Items(ref: '#/components/schemas/ApiKeyItem')),
        new OA\Property(property: 'meta', ref: '#/components/schemas/Meta'),
    ],
)]

// -- Organizations --

#[OA\Schema(
    schema: 'Organization',
    properties: [
        new OA\Property(property: 'organization_id', type: 'string'),
        new OA\Property(property: 'legal_name', type: 'string'),
        new OA\Property(property: 'display_name', type: 'string'),
        new OA\Property(property: 'tax_id', type: 'string', nullable: true),
        new OA\Property(property: 'contact_email', type: 'string'),
        new OA\Property(property: 'contact_phone', type: 'string'),
        new OA\Property(property: 'created_at', type: 'string', format: 'date-time'),
        new OA\Property(property: 'updated_at', type: 'string', format: 'date-time'),
    ],
)]

#[OA\Schema(
    schema: 'OrganizationCreatedResponse',
    properties: [
        new OA\Property(property: 'data', properties: [
            new OA\Property(property: 'organization_id', type: 'string'),
            new OA\Property(property: 'legal_name', type: 'string'),
            new OA\Property(property: 'display_name', type: 'string'),
            new OA\Property(property: 'tax_id', type: 'string', nullable: true),
            new OA\Property(property: 'contact_email', type: 'string'),
            new OA\Property(property: 'contact_phone', type: 'string'),
        ], type: 'object'),
        new OA\Property(property: 'meta', ref: '#/components/schemas/Meta'),
    ],
)]

#[OA\Schema(
    schema: 'OrganizationResponse',
    properties: [
        new OA\Property(property: 'data', ref: '#/components/schemas/Organization'),
        new OA\Property(property: 'meta', ref: '#/components/schemas/Meta'),
    ],
)]

#[OA\Schema(
    schema: 'OrganizationListResponse',
    properties: [
        new OA\Property(property: 'data', type: 'array', items: new OA\Items(ref: '#/components/schemas/Organization')),
        new OA\Property(property: 'meta', ref: '#/components/schemas/Meta'),
    ],
)]

// -- Admin Providers --

#[OA\Schema(
    schema: 'AdminProvider',
    properties: [
        new OA\Property(property: 'provider_id', type: 'string'),
        new OA\Property(property: 'organization_id', type: 'string', nullable: true),
        new OA\Property(property: 'display_name', type: 'string', nullable: true),
        new OA\Property(property: 'provider_type', type: 'string'),
        new OA\Property(property: 'status', type: 'string'),
        new OA\Property(property: 'created_at', type: 'string', format: 'date-time'),
        new OA\Property(property: 'updated_at', type: 'string', format: 'date-time'),
    ],
)]

#[OA\Schema(
    schema: 'AdminProviderCreatedResponse',
    properties: [
        new OA\Property(property: 'data', properties: [
            new OA\Property(property: 'provider_id', type: 'string'),
            new OA\Property(property: 'organization_id', type: 'string'),
            new OA\Property(property: 'display_name', type: 'string', nullable: true),
            new OA\Property(property: 'provider_type', type: 'string'),
            new OA\Property(property: 'status', type: 'string'),
        ], type: 'object'),
        new OA\Property(property: 'meta', ref: '#/components/schemas/Meta'),
    ],
)]

#[OA\Schema(
    schema: 'AdminProviderResponse',
    properties: [
        new OA\Property(property: 'data', ref: '#/components/schemas/AdminProvider'),
        new OA\Property(property: 'meta', ref: '#/components/schemas/Meta'),
    ],
)]

#[OA\Schema(
    schema: 'AdminProviderListResponse',
    properties: [
        new OA\Property(property: 'data', type: 'array', items: new OA\Items(ref: '#/components/schemas/AdminProvider')),
        new OA\Property(property: 'meta', ref: '#/components/schemas/Meta'),
    ],
)]

// -- Users --

#[OA\Schema(
    schema: 'User',
    properties: [
        new OA\Property(property: 'user_id', type: 'string'),
        new OA\Property(property: 'provider_id', type: 'string'),
        new OA\Property(property: 'first_name', type: 'string'),
        new OA\Property(property: 'last_name', type: 'string'),
        new OA\Property(property: 'email', type: 'string'),
        new OA\Property(property: 'phone', type: 'string'),
        new OA\Property(property: 'status', type: 'string'),
        new OA\Property(property: 'roles', type: 'array', items: new OA\Items(type: 'string')),
        new OA\Property(property: 'created_at', type: 'string', format: 'date-time'),
        new OA\Property(property: 'updated_at', type: 'string', format: 'date-time'),
    ],
)]

#[OA\Schema(
    schema: 'UserCreatedResponse',
    properties: [
        new OA\Property(property: 'data', properties: [
            new OA\Property(property: 'user_id', type: 'string'),
            new OA\Property(property: 'first_name', type: 'string'),
            new OA\Property(property: 'last_name', type: 'string'),
            new OA\Property(property: 'email', type: 'string'),
            new OA\Property(property: 'phone', type: 'string'),
            new OA\Property(property: 'provider_id', type: 'string'),
            new OA\Property(property: 'roles', type: 'array', items: new OA\Items(type: 'string')),
            new OA\Property(property: 'status', type: 'string'),
            new OA\Property(property: 'password_set', type: 'boolean'),
        ], type: 'object'),
        new OA\Property(property: 'meta', ref: '#/components/schemas/Meta'),
    ],
)]

#[OA\Schema(
    schema: 'UserResponse',
    properties: [
        new OA\Property(property: 'data', ref: '#/components/schemas/User'),
        new OA\Property(property: 'meta', ref: '#/components/schemas/Meta'),
    ],
)]

#[OA\Schema(
    schema: 'UserListResponse',
    properties: [
        new OA\Property(property: 'data', type: 'array', items: new OA\Items(ref: '#/components/schemas/User')),
        new OA\Property(property: 'meta', ref: '#/components/schemas/Meta'),
    ],
)]

// -- Providers (self-service) --

#[OA\Schema(
    schema: 'ProviderCreatedResponse',
    properties: [
        new OA\Property(property: 'data', properties: [
            new OA\Property(property: 'provider_id', type: 'string'),
            new OA\Property(property: 'provider_type', type: 'string'),
            new OA\Property(property: 'status', type: 'string'),
        ], type: 'object'),
        new OA\Property(property: 'meta', ref: '#/components/schemas/Meta'),
    ],
)]

// -- Openings --

#[OA\Schema(
    schema: 'OpeningCreatedResponse',
    properties: [
        new OA\Property(property: 'data', properties: [
            new OA\Property(property: 'opening_id', type: 'string'),
            new OA\Property(property: 'provider_id', type: 'string'),
            new OA\Property(property: 'service_offering_id', type: 'string'),
            new OA\Property(property: 'starts_at', type: 'string', format: 'date-time'),
            new OA\Property(property: 'ends_at', type: 'string', format: 'date-time'),
            new OA\Property(property: 'status', type: 'string', example: 'draft'),
        ], type: 'object'),
        new OA\Property(property: 'meta', ref: '#/components/schemas/Meta'),
    ],
)]

// -- Bookings --

#[OA\Schema(
    schema: 'BookingCreatedResponse',
    properties: [
        new OA\Property(property: 'data', properties: [
            new OA\Property(property: 'booking_id', type: 'string'),
            new OA\Property(property: 'opening_id', type: 'string'),
            new OA\Property(property: 'state', type: 'string', example: 'reserved'),
            new OA\Property(property: 'reserved_at', type: 'string', format: 'date-time'),
            new OA\Property(property: 'expires_at', type: 'string', format: 'date-time'),
        ], type: 'object'),
        new OA\Property(property: 'meta', ref: '#/components/schemas/Meta'),
    ],
)]

// -- Payments --

#[OA\Schema(
    schema: 'PaymentInitiatedResponse',
    properties: [
        new OA\Property(property: 'data', properties: [
            new OA\Property(property: 'payment_id', type: 'string'),
            new OA\Property(property: 'state', type: 'string'),
            new OA\Property(property: 'amount', properties: [
                new OA\Property(property: 'currency', type: 'string'),
                new OA\Property(property: 'amount_minor', type: 'integer'),
            ], type: 'object'),
            new OA\Property(property: 'gateway_status', properties: [
                new OA\Property(property: 'provider', type: 'string', example: 'stripe'),
                new OA\Property(property: 'status', type: 'string'),
            ], type: 'object'),
            new OA\Property(property: 'stripe', properties: [
                new OA\Property(property: 'payment_intent_id', type: 'string'),
                new OA\Property(property: 'client_secret', type: 'string'),
            ], type: 'object'),
        ], type: 'object'),
        new OA\Property(property: 'meta', ref: '#/components/schemas/Meta'),
    ],
)]

final class OpenApiSchemas
{
}

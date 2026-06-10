<?php

declare(strict_types=1);

namespace App\Modules\Organizations\Application\Service;

use App\Common\Api\ApiError;
use App\Common\Api\ApiException;
use App\Common\Security\ActorContext;
use App\Modules\AdminSetup\Application\Port\OrganizationRepository;
use App\Modules\Organizations\Application\Port\OrganizationMemberRepository;
use App\Platform\Persistence\TransactionManager;

final class CreateOrganizationSelfService
{
    public function __construct(
        private TransactionManager $tx,
        private OrganizationRepository $organizations,
        private OrganizationMemberRepository $members,
    ) {
    }

    /**
     * Self-service organization creation: the creating provider actor
     * becomes the organization owner member.
     *
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    public function create(ActorContext $actor, array $payload): array
    {
        if (!$actor->hasRole('provider')) {
            throw new ApiException(403, new ApiError('FORBIDDEN_ROLE_MISSING', 'Provider role is required.'));
        }
        if ($actor->userProfileId === null) {
            throw new ApiException(401, new ApiError('AUTH_IDENTITY_NOT_LINKED', 'User profile linkage is required.'));
        }

        foreach (['legal_name', 'display_name', 'contact_email', 'contact_phone'] as $field) {
            if (trim((string) ($payload[$field] ?? '')) === '') {
                throw new ApiException(422, new ApiError('VALIDATION_REQUIRED_FIELD_MISSING', sprintf('%s is required.', $field)));
            }
        }
        if (filter_var((string) $payload['contact_email'], FILTER_VALIDATE_EMAIL) === false) {
            throw new ApiException(422, new ApiError('VALIDATION_CONTACT_EMAIL_INVALID', 'contact_email must be a valid email address.'));
        }

        return $this->tx->withinTransaction(function () use ($actor, $payload): array {
            $organization = $this->organizations->create([
                'legal_name' => trim((string) $payload['legal_name']),
                'display_name' => trim((string) $payload['display_name']),
                'tax_id' => isset($payload['tax_id']) ? (string) $payload['tax_id'] : null,
                'contact_email' => (string) $payload['contact_email'],
                'contact_phone' => (string) $payload['contact_phone'],
            ]);

            $member = $this->members->addMember((string) $organization['organization_id'], (string) $actor->userProfileId, 'owner');

            return $organization + ['members' => [$member]];
        });
    }
}

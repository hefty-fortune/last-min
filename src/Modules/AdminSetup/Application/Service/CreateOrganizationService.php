<?php

declare(strict_types=1);

namespace App\Modules\AdminSetup\Application\Service;

use App\Common\Api\ApiError;
use App\Common\Api\ApiException;
use App\Common\Security\ActorContext;
use App\Modules\AdminSetup\Application\Dto\CreateOrganizationRequest;
use App\Modules\AdminSetup\Application\Port\OrganizationRepository;

final class CreateOrganizationService
{
    public function __construct(private OrganizationRepository $organizations)
    {
    }

    public function create(ActorContext $actor, CreateOrganizationRequest $request): array
    {
        $this->assertAdmin($actor);
        $this->assertNotBlank($request->legalName, 'legal_name');
        $this->assertNotBlank($request->displayName, 'display_name');
        $this->assertNotBlank($request->contactEmail, 'contact_email');
        $this->assertNotBlank($request->contactPhone, 'contact_phone');

        if (filter_var($request->contactEmail, FILTER_VALIDATE_EMAIL) === false) {
            throw new ApiException(422, new ApiError('VALIDATION_CONTACT_EMAIL_INVALID', 'contact_email must be a valid email address.'));
        }

        return $this->organizations->create([
            'legal_name' => $request->legalName,
            'display_name' => $request->displayName,
            'tax_id' => $request->taxId,
            'contact_email' => $request->contactEmail,
            'contact_phone' => $request->contactPhone,
        ]);
    }

    private function assertAdmin(ActorContext $actor): void
    {
        if (!$actor->hasRole('admin') && !$actor->hasRole('super-admin')) {
            throw new ApiException(403, new ApiError('FORBIDDEN_ROLE_MISSING', 'Admin role is required.'));
        }
    }

    private function assertNotBlank(string $value, string $field): void
    {
        if (trim($value) === '') {
            throw new ApiException(422, new ApiError('VALIDATION_REQUIRED_FIELD_MISSING', sprintf('%s is required.', $field)));
        }
    }
}

<?php

declare(strict_types=1);

namespace App\Modules\AdminSetup\Application\Service;

use App\Common\Api\ApiError;
use App\Common\Api\ApiException;
use App\Common\Security\ActorContext;
use App\Modules\AdminSetup\Application\Dto\CreateUserRequest;
use App\Modules\AdminSetup\Application\Port\AdminProviderRepository;
use App\Modules\AdminSetup\Application\Port\UserRepository;

final class CreateUserService
{
    public function __construct(
        private AdminProviderRepository $providers,
        private UserRepository $users,
    ) {
    }

    public function create(ActorContext $actor, CreateUserRequest $request): array
    {
        $this->assertAdmin($actor);
        $this->assertNotBlank($request->firstName, 'first_name');
        $this->assertNotBlank($request->lastName, 'last_name');
        $this->assertNotBlank($request->email, 'email');
        $this->assertNotBlank($request->phone, 'phone');

        if (filter_var($request->email, FILTER_VALIDATE_EMAIL) === false) {
            throw new ApiException(422, new ApiError('VALIDATION_EMAIL_INVALID', 'email must be a valid email address.'));
        }
        if (!$this->providers->existsById($request->providerId)) {
            throw new ApiException(422, new ApiError('VALIDATION_PROVIDER_NOT_FOUND', 'provider_id does not reference an existing provider.'));
        }
        if ($request->roles === []) {
            throw new ApiException(422, new ApiError('VALIDATION_ROLES_REQUIRED', 'roles must contain at least one role.'));
        }

        $roles = [];
        foreach ($request->roles as $role) {
            $normalizedRole = trim((string) $role);
            if ($normalizedRole === '') {
                throw new ApiException(422, new ApiError('VALIDATION_ROLES_INVALID', 'roles must not contain empty values.'));
            }
            $roles[] = $normalizedRole;
        }

        $passwordHash = null;
        if ($request->password !== null && trim($request->password) !== '') {
            $passwordHash = password_hash($request->password, PASSWORD_BCRYPT);
        }

        return $this->users->create([
            'first_name' => $request->firstName,
            'last_name' => $request->lastName,
            'email' => $request->email,
            'phone' => $request->phone,
            'provider_id' => $request->providerId,
            'password_hash' => $passwordHash,
        ], array_values(array_unique($roles)));
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

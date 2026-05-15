<?php

declare(strict_types=1);

namespace App\Modules\AdminSetup\Application\Service;

use App\Common\Api\ApiError;
use App\Common\Api\ApiException;
use App\Common\Security\ActorContext;
use App\Modules\AdminSetup\Application\Port\UserRepository;

final class UpdateUserService
{
    public function __construct(private UserRepository $users)
    {
    }

    public function update(ActorContext $actor, string $userId, array $fields): array
    {
        $this->assertAdmin($actor);

        $user = $this->users->getById($userId);
        if ($user === null) {
            throw new ApiException(404, new ApiError('USER_NOT_FOUND', 'User not found.'));
        }

        $allowedFields = ['first_name', 'last_name', 'email', 'phone'];
        $updateData = [];

        foreach ($allowedFields as $field) {
            if (array_key_exists($field, $fields)) {
                $value = trim((string) $fields[$field]);
                if ($value === '') {
                    throw new ApiException(422, new ApiError('VALIDATION_REQUIRED_FIELD_MISSING', sprintf('%s must not be blank.', $field)));
                }
                $updateData[$field] = $value;
            }
        }

        if ($updateData === []) {
            throw new ApiException(422, new ApiError('VALIDATION_NO_FIELDS', 'At least one field must be provided for update.'));
        }

        if (isset($updateData['email']) && filter_var($updateData['email'], FILTER_VALIDATE_EMAIL) === false) {
            throw new ApiException(422, new ApiError('VALIDATION_EMAIL_INVALID', 'email must be a valid email address.'));
        }

        return $this->users->updateFields($userId, $updateData);
    }

    private function assertAdmin(ActorContext $actor): void
    {
        if (!$actor->hasRole('admin') && !$actor->hasRole('super-admin') && !$actor->hasRole('org_admin')) {
            throw new ApiException(403, new ApiError('FORBIDDEN_ROLE_MISSING', 'Admin or org_admin role is required.'));
        }
    }
}

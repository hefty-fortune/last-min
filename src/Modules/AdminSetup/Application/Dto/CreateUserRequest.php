<?php

declare(strict_types=1);

namespace App\Modules\AdminSetup\Application\Dto;

final readonly class CreateUserRequest
{
    /** @param list<string> $roles */
    public function __construct(
        public string $firstName,
        public string $lastName,
        public string $email,
        public string $phone,
        public array $roles,
        public string $providerId,
        public ?string $password = null,
    ) {
    }
}

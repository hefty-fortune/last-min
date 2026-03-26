<?php

declare(strict_types=1);

namespace App\Modules\Openings\Application\Port;

interface OpeningRepository
{
    public function createDraft(array $data): array;
    public function lockById(string $openingId): ?array;
    public function updateStatus(string $openingId, string $status): void;
}

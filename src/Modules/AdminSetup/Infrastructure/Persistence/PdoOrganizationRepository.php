<?php

declare(strict_types=1);

namespace App\Modules\AdminSetup\Infrastructure\Persistence;

use App\Modules\AdminSetup\Application\Port\OrganizationRepository;
use PDO;

final class PdoOrganizationRepository implements OrganizationRepository
{
    public function __construct(private PDO $pdo)
    {
    }

    public function create(array $organization): array
    {
        $id = self::uuid();
        $now = (new \DateTimeImmutable())->format(DATE_ATOM);
        $stmt = $this->pdo->prepare('INSERT INTO organizations (id, legal_name, display_name, tax_id, contact_email, contact_phone, created_at, updated_at) VALUES (:id, :legal_name, :display_name, :tax_id, :contact_email, :contact_phone, :created_at, :updated_at)');
        $stmt->execute([
            'id' => $id,
            'legal_name' => $organization['legal_name'],
            'display_name' => $organization['display_name'],
            'tax_id' => $organization['tax_id'],
            'contact_email' => $organization['contact_email'],
            'contact_phone' => $organization['contact_phone'],
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        return [
            'organization_id' => $id,
            'legal_name' => $organization['legal_name'],
            'display_name' => $organization['display_name'],
            'tax_id' => $organization['tax_id'],
            'contact_email' => $organization['contact_email'],
            'contact_phone' => $organization['contact_phone'],
        ];
    }

    public function existsById(string $organizationId): bool
    {
        $stmt = $this->pdo->prepare('SELECT 1 FROM organizations WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $organizationId]);

        return $stmt->fetchColumn() !== false;
    }

    private static function uuid(): string
    {
        $data = random_bytes(16);
        $data[6] = chr((ord($data[6]) & 0x0f) | 0x40);
        $data[8] = chr((ord($data[8]) & 0x3f) | 0x80);
        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
    }
}

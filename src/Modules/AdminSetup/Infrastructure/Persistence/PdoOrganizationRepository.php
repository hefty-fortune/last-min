<?php

declare(strict_types=1);

namespace App\Modules\AdminSetup\Infrastructure\Persistence;

use App\Common\Api\ApiError;
use App\Common\Api\ApiException;
use App\Modules\AdminSetup\Application\Port\OrganizationRepository;
use PDO;
use PDOException;

final class PdoOrganizationRepository implements OrganizationRepository
{
    public function __construct(private PDO $pdo)
    {
    }

    public function create(array $organization): array
    {
        $id = self::uuid();
        $now = (new \DateTimeImmutable())->format(DATE_ATOM);
        try {
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
        } catch (PDOException $e) {
            if ($this->isUniqueViolation($e, ['organizations.legal_name', 'idx_organizations_legal_name', 'key (legal_name)'])) {
                throw new ApiException(409, new ApiError('CONFLICT_ORGANIZATION_LEGAL_NAME_EXISTS', 'An organization with this legal_name already exists.'));
            }

            throw $e;
        }

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

    public function getById(string $organizationId): ?array
    {
        $stmt = $this->pdo->prepare('SELECT id, legal_name, display_name, tax_id, contact_email, contact_phone, created_at, updated_at FROM organizations WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $organizationId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row === false) {
            return null;
        }

        return [
            'organization_id' => (string) $row['id'],
            'legal_name' => (string) $row['legal_name'],
            'display_name' => (string) $row['display_name'],
            'tax_id' => $row['tax_id'] !== null ? (string) $row['tax_id'] : null,
            'contact_email' => (string) $row['contact_email'],
            'contact_phone' => (string) $row['contact_phone'],
            'created_at' => (string) $row['created_at'],
            'updated_at' => (string) $row['updated_at'],
        ];
    }

    public function listAll(): array
    {
        $stmt = $this->pdo->query('SELECT id, legal_name, display_name, tax_id, contact_email, contact_phone, created_at, updated_at FROM organizations ORDER BY created_at ASC');
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return array_map(static fn (array $row): array => [
            'organization_id' => (string) $row['id'],
            'legal_name' => (string) $row['legal_name'],
            'display_name' => (string) $row['display_name'],
            'tax_id' => $row['tax_id'] !== null ? (string) $row['tax_id'] : null,
            'contact_email' => (string) $row['contact_email'],
            'contact_phone' => (string) $row['contact_phone'],
            'created_at' => (string) $row['created_at'],
            'updated_at' => (string) $row['updated_at'],
        ], $rows);
    }

    /** @param list<string> $needles */
    private function isUniqueViolation(PDOException $e, array $needles): bool
    {
        $sqlState = (string) ($e->errorInfo[0] ?? '');
        if ($sqlState !== '23000' && $sqlState !== '23505') {
            return false;
        }

        $haystacks = [strtolower($e->getMessage())];
        if (isset($e->errorInfo[2]) && is_string($e->errorInfo[2])) {
            $haystacks[] = strtolower($e->errorInfo[2]);
        }

        foreach ($needles as $needle) {
            $normalizedNeedle = strtolower($needle);
            foreach ($haystacks as $haystack) {
                if (str_contains($haystack, $normalizedNeedle)) {
                    return true;
                }
            }
        }

        return false;
    }

    private static function uuid(): string
    {
        $data = random_bytes(16);
        $data[6] = chr((ord($data[6]) & 0x0f) | 0x40);
        $data[8] = chr((ord($data[8]) & 0x3f) | 0x80);
        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
    }
}

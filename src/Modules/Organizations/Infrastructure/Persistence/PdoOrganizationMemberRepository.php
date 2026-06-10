<?php

declare(strict_types=1);

namespace App\Modules\Organizations\Infrastructure\Persistence;

use App\Common\Api\ApiError;
use App\Common\Api\ApiException;
use App\Modules\Organizations\Application\Port\OrganizationMemberRepository;
use PDO;
use PDOException;

final class PdoOrganizationMemberRepository implements OrganizationMemberRepository
{
    public function __construct(private PDO $pdo)
    {
    }

    public function findMember(string $organizationId, string $userProfileId): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM organization_members WHERE organization_id = :organization_id AND user_profile_id = :user_profile_id LIMIT 1');
        $stmt->execute(['organization_id' => $organizationId, 'user_profile_id' => $userProfileId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row === false ? null : $this->mapMember($row);
    }

    public function listMembers(string $organizationId): array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM organization_members WHERE organization_id = :organization_id ORDER BY created_at ASC');
        $stmt->execute(['organization_id' => $organizationId]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return array_map(fn (array $row): array => $this->mapMember($row), $rows);
    }

    public function addMember(string $organizationId, string $userProfileId, string $organizationRole): array
    {
        $id = self::uuid();
        $now = (new \DateTimeImmutable())->format(DATE_ATOM);
        try {
            $stmt = $this->pdo->prepare('INSERT INTO organization_members (id, organization_id, user_profile_id, organization_role, created_at, updated_at) VALUES (:id, :organization_id, :user_profile_id, :organization_role, :created_at, :updated_at)');
            $stmt->execute([
                'id' => $id,
                'organization_id' => $organizationId,
                'user_profile_id' => $userProfileId,
                'organization_role' => $organizationRole,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        } catch (PDOException $e) {
            $sqlState = (string) ($e->errorInfo[0] ?? '');
            if ($sqlState === '23000' || $sqlState === '23505') {
                throw new ApiException(409, new ApiError('CONFLICT_MEMBER_ALREADY_EXISTS', 'This user is already a member of the organization.'));
            }
            throw $e;
        }

        $member = $this->findMember($organizationId, $userProfileId);
        assert($member !== null);

        return $member;
    }

    /** @param array<string, mixed> $row
     *  @return array<string, mixed>
     */
    private function mapMember(array $row): array
    {
        return [
            'member_id' => $row['id'],
            'organization_id' => $row['organization_id'],
            'user_profile_id' => $row['user_profile_id'],
            'organization_role' => $row['organization_role'],
            'created_at' => $row['created_at'],
            'updated_at' => $row['updated_at'],
        ];
    }

    private static function uuid(): string
    {
        $data = random_bytes(16);
        $data[6] = chr((ord($data[6]) & 0x0f) | 0x40);
        $data[8] = chr((ord($data[8]) & 0x3f) | 0x80);
        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
    }
}

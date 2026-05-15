#!/usr/bin/env php
<?php

declare(strict_types=1);

use App\Bootstrap\DatabaseConnection;

require __DIR__ . '/../vendor/autoload.php';

$options = getopt('', ['email:', 'password:', 'first-name::', 'last-name::']);

$email = is_string($options['email'] ?? null) ? (string) $options['email'] : '';
$password = is_string($options['password'] ?? null) ? (string) $options['password'] : '';
$firstName = is_string($options['first-name'] ?? null) && trim((string) $options['first-name']) !== ''
    ? (string) $options['first-name']
    : 'Admin';
$lastName = is_string($options['last-name'] ?? null) && trim((string) $options['last-name']) !== ''
    ? (string) $options['last-name']
    : 'User';

if (trim($email) === '' || trim($password) === '') {
    fwrite(STDERR, "Usage: php bin/create-admin-user.php --email=admin@example.com --password=secret [--first-name=Admin] [--last-name=User]\n");
    exit(1);
}

$pdo = DatabaseConnection::fromEnvironment();

// Ensure we have at least one organization and provider
$org = $pdo->query("SELECT id FROM organizations LIMIT 1")->fetch(PDO::FETCH_ASSOC);
if ($org === false) {
    $orgId = bin2hex(random_bytes(16));
    $now = (new DateTimeImmutable())->format(DATE_ATOM);
    $stmt = $pdo->prepare("INSERT INTO organizations (id, legal_name, display_name, tax_id, contact_email, contact_phone, created_at, updated_at) VALUES (:id, 'Default Org', 'Default Org', NULL, :email, '+0000000000', :now, :now)");
    $stmt->execute(['id' => $orgId, 'email' => $email, 'now' => $now]);
    fwrite(STDOUT, "Created default organization.\n");
} else {
    $orgId = (string) $org['id'];
}

$provider = $pdo->query("SELECT id FROM providers LIMIT 1")->fetch(PDO::FETCH_ASSOC);
if ($provider === false) {
    $providerId = bin2hex(random_bytes(16));
    $now = (new DateTimeImmutable())->format(DATE_ATOM);
    $stmt = $pdo->prepare("INSERT INTO providers (id, provider_type, owner_user_profile_id, organization_id, display_name, status, created_at, updated_at) VALUES (:id, 'organization', NULL, :org_id, 'Default Provider', 'active', :now, :now)");
    $stmt->execute(['id' => $providerId, 'org_id' => $orgId, 'now' => $now]);
    fwrite(STDOUT, "Created default provider.\n");
} else {
    $providerId = (string) $provider['id'];
}

// Check if user already exists
$existing = $pdo->prepare("SELECT id FROM users WHERE email = :email LIMIT 1");
$existing->execute(['email' => $email]);
if ($existing->fetch() !== false) {
    fwrite(STDERR, "User with email {$email} already exists. Use set-user-password.php to change the password.\n");
    exit(1);
}

// Create user
$userId = bin2hex(random_bytes(16));
$now = (new DateTimeImmutable())->format(DATE_ATOM);
$hash = password_hash($password, PASSWORD_BCRYPT);

$stmt = $pdo->prepare("INSERT INTO users (id, provider_id, first_name, last_name, email, phone, password_hash, status, created_at, updated_at) VALUES (:id, :provider_id, :first_name, :last_name, :email, '+0000000000', :password_hash, 'active', :now, :now)");
$stmt->execute([
    'id' => $userId,
    'provider_id' => $providerId,
    'first_name' => $firstName,
    'last_name' => $lastName,
    'email' => $email,
    'password_hash' => $hash,
    'now' => $now,
]);

// Assign admin role
$roleId = bin2hex(random_bytes(16));
$stmt = $pdo->prepare("INSERT INTO user_roles (id, user_id, role_code, created_at) VALUES (:id, :user_id, 'admin', :now)");
$stmt->execute(['id' => $roleId, 'user_id' => $userId, 'now' => $now]);

fwrite(STDOUT, "\nAdmin user created successfully!\n");
fwrite(STDOUT, "Email:    {$email}\n");
fwrite(STDOUT, "Password: {$password}\n");
fwrite(STDOUT, "Role:     admin\n");

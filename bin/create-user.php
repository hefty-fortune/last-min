#!/usr/bin/env php
<?php

declare(strict_types=1);

use App\Bootstrap\DatabaseConnection;

require __DIR__ . '/../vendor/autoload.php';

$options = getopt('', ['email:', 'password:', 'roles:', 'first-name::', 'last-name::']);

$email = is_string($options['email'] ?? null) ? trim((string) $options['email']) : '';
$password = is_string($options['password'] ?? null) ? (string) $options['password'] : '';
$rolesRaw = is_string($options['roles'] ?? null) ? (string) $options['roles'] : '';
$firstName = is_string($options['first-name'] ?? null) && trim((string) $options['first-name']) !== ''
    ? (string) $options['first-name']
    : 'Test';
$lastName = is_string($options['last-name'] ?? null) && trim((string) $options['last-name']) !== ''
    ? (string) $options['last-name']
    : 'User';

$roles = array_values(array_filter(array_map('trim', explode(',', $rolesRaw))));
$allowed = ['client', 'provider', 'admin', 'super-admin'];
$invalid = array_diff($roles, $allowed);

if ($email === '' || $password === '' || $roles === [] || $invalid !== []) {
    fwrite(STDERR, "Usage: php bin/create-user.php --email=user@example.com --password=secret --roles=client[,provider,...] [--first-name=X] [--last-name=Y]\n");
    fwrite(STDERR, "Allowed roles: " . implode(', ', $allowed) . "\n");
    exit(1);
}

$pdo = DatabaseConnection::fromEnvironment();

// users.provider_id is a legacy NOT NULL linkage; attach to the first provider.
$provider = $pdo->query('SELECT id FROM providers LIMIT 1')->fetch(PDO::FETCH_ASSOC);
if ($provider === false) {
    fwrite(STDERR, "No provider exists yet; run bin/create-admin-user.php once first.\n");
    exit(1);
}

$existing = $pdo->prepare('SELECT id FROM users WHERE email = :email LIMIT 1');
$existing->execute(['email' => $email]);
if ($existing->fetch() !== false) {
    fwrite(STDERR, "User with email {$email} already exists.\n");
    exit(1);
}

$userId = bin2hex(random_bytes(16));
$now = (new DateTimeImmutable())->format(DATE_ATOM);

$stmt = $pdo->prepare("INSERT INTO users (id, provider_id, first_name, last_name, email, phone, password_hash, status, created_at, updated_at) VALUES (:id, :provider_id, :first_name, :last_name, :email, '+0000000000', :password_hash, 'active', :now, :now)");
$stmt->execute([
    'id' => $userId,
    'provider_id' => (string) $provider['id'],
    'first_name' => $firstName,
    'last_name' => $lastName,
    'email' => $email,
    'password_hash' => password_hash($password, PASSWORD_BCRYPT),
    'now' => $now,
]);

$roleStmt = $pdo->prepare('INSERT INTO user_roles (id, user_id, role_code, created_at) VALUES (:id, :user_id, :role_code, :now)');
foreach ($roles as $role) {
    $roleStmt->execute(['id' => bin2hex(random_bytes(16)), 'user_id' => $userId, 'role_code' => $role, 'now' => $now]);
}

fwrite(STDOUT, "User created.\n");
fwrite(STDOUT, "Email:    {$email}\n");
fwrite(STDOUT, "Password: {$password}\n");
fwrite(STDOUT, "Roles:    " . implode(', ', $roles) . "\n");

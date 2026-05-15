#!/usr/bin/env php
<?php

declare(strict_types=1);

use App\Bootstrap\DatabaseConnection;

require __DIR__ . '/../vendor/autoload.php';

$options = getopt('', ['email:', 'password:']);

$email = is_string($options['email'] ?? null) ? (string) $options['email'] : '';
$password = is_string($options['password'] ?? null) ? (string) $options['password'] : '';

if (trim($email) === '' || trim($password) === '') {
    fwrite(STDERR, "Usage: php bin/set-user-password.php --email=user@example.com --password=secret\n");
    exit(1);
}

$pdo = DatabaseConnection::fromEnvironment();

$stmt = $pdo->prepare('SELECT id, email, first_name, last_name FROM users WHERE email = :email LIMIT 1');
$stmt->execute(['email' => $email]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if ($user === false) {
    fwrite(STDERR, "No user found with email: {$email}\n");
    exit(1);
}

$hash = password_hash($password, PASSWORD_BCRYPT);
$now = (new DateTimeImmutable())->format(DATE_ATOM);
$update = $pdo->prepare('UPDATE users SET password_hash = :hash, updated_at = :updated_at WHERE id = :id');
$update->execute(['hash' => $hash, 'updated_at' => $now, 'id' => $user['id']]);

fwrite(STDOUT, "Password set for user: {$user['first_name']} {$user['last_name']} ({$email})\n");

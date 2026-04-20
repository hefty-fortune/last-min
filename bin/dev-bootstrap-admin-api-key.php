#!/usr/bin/env php
<?php

declare(strict_types=1);

use App\Modules\IdentityAccess\Infrastructure\Persistence\PdoApiKeyRepository;

require __DIR__ . '/../vendor/autoload.php';

$options = getopt('', ['dsn::', 'db-user::', 'db-pass::', 'actor-id::', 'role::', 'name::']);

$dsn = is_string($options['dsn'] ?? null) && $options['dsn'] !== ''
    ? (string) $options['dsn']
    : (getenv('APP_DB_DSN') !== false ? (string) getenv('APP_DB_DSN') : 'sqlite:' . __DIR__ . '/../var/dev.sqlite');
$user = is_string($options['db-user'] ?? null) ? (string) $options['db-user'] : (getenv('APP_DB_USER') !== false ? (string) getenv('APP_DB_USER') : null);
$pass = is_string($options['db-pass'] ?? null) ? (string) $options['db-pass'] : (getenv('APP_DB_PASS') !== false ? (string) getenv('APP_DB_PASS') : null);
$actorId = is_string($options['actor-id'] ?? null) && trim((string) $options['actor-id']) !== ''
    ? (string) $options['actor-id']
    : 'local-dev-admin';
$role = is_string($options['role'] ?? null) && in_array((string) $options['role'], ['admin', 'super-admin'], true)
    ? (string) $options['role']
    : 'admin';
$name = is_string($options['name'] ?? null) && trim((string) $options['name']) !== ''
    ? (string) $options['name']
    : 'Local FE dev bootstrap key';

$token = 'lm_' . bin2hex(random_bytes(20));

$pdo = new PDO($dsn, $user, $pass, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$schema = file_get_contents(__DIR__ . '/../migrations/20260326_000001_milestone1.sql');
if ($schema === false) {
    fwrite(STDERR, "Unable to read migration file.\n");
    exit(1);
}
try {
    $pdo->exec($schema);
} catch (PDOException $e) {
    if (!str_contains(strtolower($e->getMessage()), 'already exists')) {
        fwrite(STDERR, "Unable to initialize schema: {$e->getMessage()}\n");
        exit(1);
    }
}

$repo = new PdoApiKeyRepository($pdo);
$created = $repo->createForActor($role, $actorId, [$role], $name, $token);

fwrite(STDOUT, "Created development admin API key. Store this token now; it will not be shown again.\n");
fwrite(STDOUT, "api_key_id: {$created['api_key_id']}\n");
fwrite(STDOUT, "actor_type: {$created['actor_type']}\n");
fwrite(STDOUT, "actor_id: {$created['actor_id']}\n");
fwrite(STDOUT, "token: {$token}\n");

#!/usr/bin/env php
<?php

declare(strict_types=1);

use App\Bootstrap\DatabaseConnection;
use App\Modules\IdentityAccess\Infrastructure\Persistence\PdoApiKeyRepository;

require __DIR__ . '/../vendor/autoload.php';

$options = getopt('', ['name::']);

$name = is_string($options['name'] ?? null) && trim((string) $options['name']) !== ''
    ? (string) $options['name']
    : 'Frontend Application Key';

$token = 'lm_' . bin2hex(random_bytes(20));

$pdo = DatabaseConnection::fromEnvironment();

$repo = new PdoApiKeyRepository($pdo);
$created = $repo->createForActor('frontend', 'frontend-app', ['frontend'], $name, $token);

fwrite(STDOUT, "Generated frontend API key. Add this to your frontend .env file:\n\n");
fwrite(STDOUT, "VITE_API_KEY={$token}\n\n");
fwrite(STDOUT, "api_key_id: {$created['api_key_id']}\n");
fwrite(STDOUT, "name: {$created['name']}\n");

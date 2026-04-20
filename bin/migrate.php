#!/usr/bin/env php
<?php

declare(strict_types=1);

use App\Bootstrap\DatabaseConnection;

require __DIR__ . '/../vendor/autoload.php';

$pdo = DatabaseConnection::fromEnvironment();

$pdo->exec('CREATE TABLE IF NOT EXISTS migrations (version VARCHAR(255) PRIMARY KEY, applied_at TEXT NOT NULL)');
$applied = $pdo->query('SELECT version FROM migrations')->fetchAll(PDO::FETCH_COLUMN);
$appliedSet = array_fill_keys(array_map(static fn ($value): string => (string) $value, $applied), true);

$migrationFiles = glob(__DIR__ . '/../migrations/*.sql');
sort($migrationFiles);

$appliedCount = 0;

foreach ($migrationFiles as $migrationFile) {
    $version = basename($migrationFile);
    if (isset($appliedSet[$version])) {
        continue;
    }

    $sql = file_get_contents($migrationFile);
    if ($sql === false) {
        fwrite(STDERR, "Failed to read migration {$version}\n");
        exit(1);
    }

    $pdo->beginTransaction();
    try {
        $pdo->exec($sql);
        $stmt = $pdo->prepare('INSERT INTO migrations (version, applied_at) VALUES (:version, :applied_at)');
        $stmt->execute([
            'version' => $version,
            'applied_at' => (new DateTimeImmutable())->format(DATE_ATOM),
        ]);
        $pdo->commit();
    } catch (Throwable $throwable) {
        $pdo->rollBack();
        fwrite(STDERR, "Migration failed ({$version}): {$throwable->getMessage()}\n");
        exit(1);
    }

    $appliedCount++;
    echo "Applied migration: {$version}\n";
}

echo $appliedCount === 0
    ? "No pending migrations.\n"
    : "Applied {$appliedCount} migration(s).\n";

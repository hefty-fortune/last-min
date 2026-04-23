<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Bootstrap\AppKernel;
use App\Common\Http\Request;
use PDO;
use PHPUnit\Framework\TestCase;

final class AppKernelWiringTest extends TestCase
{
    public function testBuildRouterDoesNotCrashWithAdminControllerDependencies(): void
    {
        $pdo = new PDO('sqlite::memory:');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $schema = file_get_contents(__DIR__ . '/../../migrations/20260326_000001_milestone1.sql');
        self::assertNotFalse($schema);
        $pdo->exec($schema);

        $router = AppKernel::buildRouter($pdo, 'test_webhook_secret');
        $response = $router->dispatch(new Request('GET', '/'));

        self::assertSame(404, $response->statusCode);
        self::assertSame('ROUTE_NOT_FOUND', $response->body['error']['code']);
    }
}

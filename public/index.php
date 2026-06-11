<?php

declare(strict_types=1);

use App\Bootstrap\AppKernel;
use App\Bootstrap\DatabaseConnection;
use App\Common\Api\ApiError;
use App\Common\Api\ApiException;
use App\Common\Http\Request;

require __DIR__ . '/../vendor/autoload.php';

// Serve API documentation routes (outside the JSON API router)
$docPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
if ($docPath === '/api/docs' || $docPath === '/api/docs/') {
    (new \App\Bootstrap\Api\DocsController())->ui();
    exit;
}
if ($docPath === '/api/docs/openapi.json') {
    (new \App\Bootstrap\Api\DocsController())->spec();
    exit;
}

$pdo = DatabaseConnection::fromEnvironment();
$stripeWebhookSecret = getenv('STRIPE_WEBHOOK_SECRET') ?: 'dev-stripe-webhook-secret';
$stripeMode = getenv('STRIPE_MODE') ?: 'simulation';
$stripeSecretKey = getenv('STRIPE_SECRET_KEY') ?: '';

$headers = function_exists('getallheaders') ? getallheaders() : [];
$rawBody = file_get_contents('php://input');
$decoded = json_decode($rawBody !== false ? $rawBody : '', true);
$body = is_array($decoded) ? $decoded : [];

$request = new Request(
    $_SERVER['REQUEST_METHOD'] ?? 'GET',
    $_SERVER['REQUEST_URI'] ?? '/',
    $headers,
    $body,
    rawBody: $rawBody !== false ? $rawBody : '',
);

try {
    $response = AppKernel::buildRouter($pdo, $stripeWebhookSecret, $stripeMode, $stripeSecretKey)->dispatch($request);
} catch (ApiException $e) {
    $response = new \App\Common\Api\ApiResponse($e->statusCode, $e->error->toArray());
} catch (\Throwable) {
    $response = new \App\Common\Api\ApiResponse(
        500,
        (new ApiError('INTERNAL_SERVER_ERROR', 'An unexpected server error occurred.'))->toArray(),
    );
}

http_response_code($response->statusCode);
header('Content-Type: application/json');
echo json_encode($response->body, JSON_THROW_ON_ERROR);

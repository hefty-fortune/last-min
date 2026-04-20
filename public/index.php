<?php

declare(strict_types=1);

use App\Bootstrap\AppKernel;
use App\Bootstrap\DatabaseConnection;
use App\Common\Http\Request;

require __DIR__ . '/../vendor/autoload.php';

$pdo = DatabaseConnection::fromEnvironment();
$stripeWebhookSecret = getenv('STRIPE_WEBHOOK_SECRET') ?: 'dev-stripe-webhook-secret';

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

$response = AppKernel::buildRouter($pdo, $stripeWebhookSecret)->dispatch($request);

http_response_code($response->statusCode);
header('Content-Type: application/json');
echo json_encode($response->body, JSON_THROW_ON_ERROR);

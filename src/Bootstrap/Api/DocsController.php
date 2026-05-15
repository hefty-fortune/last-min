<?php

declare(strict_types=1);

namespace App\Bootstrap\Api;

final class DocsController
{
    public function spec(): void
    {
        $generator = new \OpenApi\Generator(logger: new \Psr\Log\NullLogger());
        $openapi = $generator->generate([__DIR__ . '/../../']);

        header('Content-Type: application/json');
        header('Access-Control-Allow-Origin: *');
        echo $openapi->toJson();
    }

    public function ui(): void
    {
        $specUrl = '/api/docs/openapi.json';

        header('Content-Type: text/html');
        echo <<<HTML
        <!DOCTYPE html>
        <html lang="en">
        <head>
            <meta charset="UTF-8">
            <title>LastMin API Docs</title>
            <link rel="stylesheet" href="https://unpkg.com/swagger-ui-dist@5/swagger-ui.css">
        </head>
        <body>
            <div id="swagger-ui"></div>
            <script src="https://unpkg.com/swagger-ui-dist@5/swagger-ui-bundle.js"></script>
            <script>
                SwaggerUIBundle({
                    url: '{$specUrl}',
                    dom_id: '#swagger-ui',
                    presets: [SwaggerUIBundle.presets.apis, SwaggerUIBundle.SwaggerUIStandalonePreset],
                    layout: 'BaseLayout',
                });
            </script>
        </body>
        </html>
        HTML;
    }
}

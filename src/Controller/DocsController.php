<?php

namespace App\Controller;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class DocsController
{
    public function __construct(
        #[Autowire(param: 'kernel.project_dir')]
        private readonly string $projectDir,
    ) {
    }

    #[Route('/docs', name: 'docs_ui', methods: ['GET'])]
    public function ui(): Response
    {
        return new Response(<<<'HTML'
            <!doctype html>
            <html>
              <head>
                <title>Webhook Debugger API — Docs</title>
                <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/swagger-ui-dist@5/swagger-ui.css">
              </head>
              <body>
                <div id="swagger-ui"></div>
                <script src="https://cdn.jsdelivr.net/npm/swagger-ui-dist@5/swagger-ui-bundle.js"></script>
                <script>
                  window.ui = SwaggerUIBundle({
                    url: '/docs/openapi.yaml',
                    dom_id: '#swagger-ui',
                  });
                </script>
              </body>
            </html>
            HTML);
    }

    #[Route('/docs/openapi.yaml', name: 'docs_spec', methods: ['GET'])]
    public function spec(): Response
    {
        return new Response(
            file_get_contents($this->projectDir.'/openapi.yaml'),
            200,
            ['Content-Type' => 'application/yaml'],
        );
    }
}

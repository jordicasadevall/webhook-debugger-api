<?php

namespace App\EventListener;

use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Event\RequestEvent;

/**
 * Rejects requests that don't carry RapidAPI's proxy secret, so the API can't
 * be called for free by hitting the origin host directly and skipping
 * RapidAPI's auth/quota/billing. The webhook receiver and docs routes are
 * exempt: providers call /in/{inboxId} directly, never through RapidAPI, and
 * the docs need to stay publicly browsable.
 *
 * Runs at a priority below the router (32), so routing has already resolved
 * `_route` by the time this fires.
 */
#[AsEventListener(event: 'kernel.request', priority: 0)]
class RapidApiProxyGuard
{
    private const EXEMPT_ROUTES = ['webhook_receive', 'docs_ui', 'docs_spec'];

    public function __construct(
        #[Autowire('%env(RAPIDAPI_PROXY_SECRET)%')]
        private readonly string $expectedSecret,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function __invoke(RequestEvent $event): void
    {
        // Empty means the deployment hasn't configured RapidAPI enforcement
        // (e.g. local dev/tests) — stay a no-op rather than lock everything out.
        if ('' === $this->expectedSecret || !$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();

        if (in_array($request->attributes->get('_route'), self::EXEMPT_ROUTES, true)) {
            return;
        }

        $provided = $request->headers->get('X-RapidAPI-Proxy-Secret') ?? '';

        if (!hash_equals($this->expectedSecret, $provided)) {
            $this->logger->warning('Blocked request: direct API access bypassing RapidAPI', [
                'code' => 'forbidden',
                'path' => $request->getPathInfo(),
                'ip' => $request->getClientIp(),
            ]);

            $event->setResponse(new JsonResponse(
                ['error' => ['code' => 'forbidden', 'message' => 'This API must be called through RapidAPI']],
                403,
            ));
        }
    }
}

<?php

namespace App\EventListener;

use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Event\RequestEvent;

/**
 * Rejects requests that don't carry a shared secret, so the API can't be
 * called for free by hitting the origin host directly and skipping an API
 * gateway's (e.g. RapidAPI) auth/quota/billing. The webhook receiver and
 * docs routes are exempt: providers call /in/{inboxId} directly, never
 * through the gateway, and the docs need to stay publicly browsable.
 *
 * Runs at a priority below the router (32), so routing has already resolved
 * `_route` by the time this fires.
 */
#[AsEventListener(event: 'kernel.request', priority: 0)]
class GatewaySecretGuard
{
    private const EXEMPT_ROUTES = ['webhook_receive', 'docs_ui', 'docs_spec'];

    public function __construct(
        #[Autowire('%env(GATEWAY_SECRET)%')]
        private readonly string $expectedSecret,
        #[Autowire('%env(GATEWAY_SECRET_HEADER)%')]
        private readonly string $headerName,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function __invoke(RequestEvent $event): void
    {
        // Empty means the deployment hasn't configured gateway enforcement
        // (e.g. local dev/tests, or self-hosting without a gateway in front)
        // — stay a no-op rather than lock everything out.
        if ('' === $this->expectedSecret || !$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();

        if (in_array($request->attributes->get('_route'), self::EXEMPT_ROUTES, true)) {
            return;
        }

        $provided = $request->headers->get($this->headerName) ?? '';

        if (!hash_equals($this->expectedSecret, $provided)) {
            $this->logger->warning('Blocked request: direct API access bypassing the gateway', [
                'code' => 'forbidden',
                'path' => $request->getPathInfo(),
                'ip' => $request->getClientIp(),
            ]);

            $event->setResponse(new JsonResponse(
                ['error' => ['code' => 'forbidden', 'message' => 'A valid gateway secret is required']],
                403,
            ));
        }
    }
}

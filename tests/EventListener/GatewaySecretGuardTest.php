<?php

namespace App\Tests\EventListener;

use App\EventListener\GatewaySecretGuard;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpKernel\KernelInterface;

class GatewaySecretGuardTest extends TestCase
{
    private function makeGuard(string $secret, string $headerName = 'X-RapidAPI-Proxy-Secret', ?LoggerInterface $logger = null): GatewaySecretGuard
    {
        return new GatewaySecretGuard($secret, $headerName, $logger ?? new NullLogger());
    }

    private function makeEvent(Request $request): RequestEvent
    {
        return new RequestEvent(
            $this->createStub(HttpKernelInterface::class),
            $request,
            KernelInterface::MAIN_REQUEST,
        );
    }

    #[Test]
    public function disabledWhenSecretNotConfigured(): void
    {
        $guard = $this->makeGuard('');
        $request = Request::create('/inboxes', 'POST');
        $request->attributes->set('_route', 'inbox_create');
        $event = $this->makeEvent($request);

        ($guard)($event);

        self::assertFalse($event->hasResponse());
    }

    #[Test]
    public function blocksRequestMissingTheSecretHeader(): void
    {
        $guard = $this->makeGuard('super-secret');
        $request = Request::create('/inboxes', 'POST');
        $request->attributes->set('_route', 'inbox_create');
        $event = $this->makeEvent($request);

        ($guard)($event);

        self::assertTrue($event->hasResponse());
        self::assertSame(403, $event->getResponse()->getStatusCode());
    }

    #[Test]
    public function blocksRequestWithWrongSecret(): void
    {
        $guard = $this->makeGuard('super-secret');
        $request = Request::create('/inboxes', 'POST', server: ['HTTP_X_RAPIDAPI_PROXY_SECRET' => 'wrong']);
        $request->attributes->set('_route', 'inbox_create');
        $event = $this->makeEvent($request);

        ($guard)($event);

        self::assertTrue($event->hasResponse());
        self::assertSame(403, $event->getResponse()->getStatusCode());
    }

    #[Test]
    public function logsBlockedRequestsForAbuseVisibility(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())->method('warning')->with(
            self::stringContains('bypassing the gateway'),
            self::callback(static fn (array $context) => 'forbidden' === $context['code'] && '/inboxes' === $context['path']),
        );
        $guard = $this->makeGuard('super-secret', logger: $logger);
        $request = Request::create('/inboxes', 'POST');
        $request->attributes->set('_route', 'inbox_create');

        ($guard)($this->makeEvent($request));
    }

    #[Test]
    public function allowsRequestWithCorrectSecret(): void
    {
        $guard = $this->makeGuard('super-secret');
        $request = Request::create('/inboxes', 'POST', server: ['HTTP_X_RAPIDAPI_PROXY_SECRET' => 'super-secret']);
        $request->attributes->set('_route', 'inbox_create');
        $event = $this->makeEvent($request);

        ($guard)($event);

        self::assertFalse($event->hasResponse());
    }

    #[Test]
    public function supportsACustomHeaderName(): void
    {
        $guard = $this->makeGuard('super-secret', 'X-Api-Gateway-Secret');
        $request = Request::create('/inboxes', 'POST', server: ['HTTP_X_API_GATEWAY_SECRET' => 'super-secret']);
        $request->attributes->set('_route', 'inbox_create');
        $event = $this->makeEvent($request);

        ($guard)($event);

        self::assertFalse($event->hasResponse());
    }

    #[Test]
    public function exemptsWebhookReceiverRegardlessOfHeader(): void
    {
        $guard = $this->makeGuard('super-secret');
        $request = Request::create('/in/00000000-0000-0000-0000-000000000000', 'POST');
        $request->attributes->set('_route', 'webhook_receive');
        $event = $this->makeEvent($request);

        ($guard)($event);

        self::assertFalse($event->hasResponse());
    }

    #[Test]
    public function exemptsDocsRoutes(): void
    {
        $guard = $this->makeGuard('super-secret');
        $request = Request::create('/docs', 'GET');
        $request->attributes->set('_route', 'docs_ui');
        $event = $this->makeEvent($request);

        ($guard)($event);

        self::assertFalse($event->hasResponse());
    }
}

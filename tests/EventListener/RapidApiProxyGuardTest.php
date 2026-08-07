<?php

namespace App\Tests\EventListener;

use App\EventListener\RapidApiProxyGuard;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpKernel\KernelInterface;

class RapidApiProxyGuardTest extends TestCase
{
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
        $guard = new RapidApiProxyGuard('');
        $request = Request::create('/inboxes', 'POST');
        $request->attributes->set('_route', 'inbox_create');
        $event = $this->makeEvent($request);

        ($guard)($event);

        self::assertFalse($event->hasResponse());
    }

    #[Test]
    public function blocksRequestMissingTheSecretHeader(): void
    {
        $guard = new RapidApiProxyGuard('super-secret');
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
        $guard = new RapidApiProxyGuard('super-secret');
        $request = Request::create('/inboxes', 'POST', server: ['HTTP_X_RAPIDAPI_PROXY_SECRET' => 'wrong']);
        $request->attributes->set('_route', 'inbox_create');
        $event = $this->makeEvent($request);

        ($guard)($event);

        self::assertTrue($event->hasResponse());
        self::assertSame(403, $event->getResponse()->getStatusCode());
    }

    #[Test]
    public function allowsRequestWithCorrectSecret(): void
    {
        $guard = new RapidApiProxyGuard('super-secret');
        $request = Request::create('/inboxes', 'POST', server: ['HTTP_X_RAPIDAPI_PROXY_SECRET' => 'super-secret']);
        $request->attributes->set('_route', 'inbox_create');
        $event = $this->makeEvent($request);

        ($guard)($event);

        self::assertFalse($event->hasResponse());
    }

    #[Test]
    public function exemptsWebhookReceiverRegardlessOfHeader(): void
    {
        $guard = new RapidApiProxyGuard('super-secret');
        $request = Request::create('/in/00000000-0000-0000-0000-000000000000', 'POST');
        $request->attributes->set('_route', 'webhook_receive');
        $event = $this->makeEvent($request);

        ($guard)($event);

        self::assertFalse($event->hasResponse());
    }

    #[Test]
    public function exemptsDocsRoutes(): void
    {
        $guard = new RapidApiProxyGuard('super-secret');
        $request = Request::create('/docs', 'GET');
        $request->attributes->set('_route', 'docs_ui');
        $event = $this->makeEvent($request);

        ($guard)($event);

        self::assertFalse($event->hasResponse());
    }
}

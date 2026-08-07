<?php

namespace App\Tests\EventListener;

use App\Exception\ApiException;
use App\EventListener\ApiExceptionListener;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpKernel\KernelInterface;

class ApiExceptionListenerTest extends TestCase
{
    private function makeEvent(Request $request, \Throwable $exception): ExceptionEvent
    {
        return new ExceptionEvent(
            $this->createStub(HttpKernelInterface::class),
            $request,
            KernelInterface::MAIN_REQUEST,
            $exception,
        );
    }

    #[Test]
    public function logsSecuritySignalCodesAsWarnings(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())->method('warning')->with(
            self::stringContains('Too many requests'),
            self::callback(static fn (array $c) => 'rate_limited' === $c['code'] && '/in/abc' === $c['path']),
        );
        $logger->expects(self::never())->method('error');

        $event = $this->makeEvent(
            Request::create('/in/abc', 'POST'),
            ApiException::tooManyRequests('rate_limited', 'Too many requests, slow down'),
        );

        (new ApiExceptionListener($logger))($event);

        self::assertSame(429, $event->getResponse()->getStatusCode());
    }

    #[Test]
    public function logsServerErrorsAsErrors(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())->method('error');
        $logger->expects(self::never())->method('warning');

        $event = $this->makeEvent(
            Request::create('/inboxes', 'POST'),
            ApiException::badGateway('replay_failed', 'Could not reach target_url'),
        );

        (new ApiExceptionListener($logger))($event);

        self::assertSame(502, $event->getResponse()->getStatusCode());
    }

    #[Test]
    public function doesNotLogPlainNotFound(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::never())->method('warning');
        $logger->expects(self::never())->method('error');

        $event = $this->makeEvent(
            Request::create('/inboxes/00000000-0000-0000-0000-000000000000', 'GET'),
            new NotFoundHttpException(),
        );

        (new ApiExceptionListener($logger))($event);

        self::assertSame(404, $event->getResponse()->getStatusCode());
    }
}

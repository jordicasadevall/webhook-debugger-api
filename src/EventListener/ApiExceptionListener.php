<?php

namespace App\EventListener;

use App\Exception\ApiException;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

#[AsEventListener(event: 'kernel.exception')]
class ApiExceptionListener
{
    public function __construct(private readonly LoggerInterface $logger)
    {
    }

    public function __invoke(ExceptionEvent $event): void
    {
        $exception = $event->getThrowable();

        [$status, $code, $message] = match (true) {
            $exception instanceof ApiException => [$exception->getStatusCode(), $exception->getErrorCode(), $exception->getMessage()],
            $exception instanceof HttpExceptionInterface && 404 === $exception->getStatusCode() => [404, 'not_found', 'Resource not found'],
            $exception instanceof HttpExceptionInterface => [$exception->getStatusCode(), 'http_error', $exception->getMessage()],
            default => [500, 'internal_error', 'Internal server error'],
        };

        if ($status >= 500) {
            $this->logger->error($exception->getMessage(), ['exception' => $exception]);
        }

        $event->setResponse(new JsonResponse(['error' => ['code' => $code, 'message' => $message]], $status));
    }
}

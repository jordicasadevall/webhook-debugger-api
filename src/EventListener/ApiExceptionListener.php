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
    /** Error codes worth watching for abuse patterns (SSRF probing, rate-limit hits) even though each one is "just" a 4xx. */
    private const SECURITY_SIGNAL_CODES = ['rate_limited', 'target_url_blocked', 'target_url_unresolvable'];

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

        $request = $event->getRequest();
        $context = ['code' => $code, 'path' => $request->getPathInfo(), 'ip' => $request->getClientIp()];

        if ($status >= 500) {
            $this->logger->error($exception->getMessage(), ['exception' => $exception] + $context);
        } elseif (in_array($code, self::SECURITY_SIGNAL_CODES, true)) {
            $this->logger->warning('Blocked request: '.$message, $context);
        }

        $event->setResponse(new JsonResponse(['error' => ['code' => $code, 'message' => $message]], $status));
    }
}

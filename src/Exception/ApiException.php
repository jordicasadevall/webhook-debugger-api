<?php

namespace App\Exception;

use Symfony\Component\HttpKernel\Exception\HttpException;

class ApiException extends HttpException
{
    public function __construct(int $statusCode, private readonly string $errorCode, string $message)
    {
        parent::__construct($statusCode, $message);
    }

    public function getErrorCode(): string
    {
        return $this->errorCode;
    }

    public static function notFound(string $errorCode, string $message): self
    {
        return new self(404, $errorCode, $message);
    }

    public static function unprocessable(string $errorCode, string $message): self
    {
        return new self(422, $errorCode, $message);
    }

    public static function payloadTooLarge(string $errorCode, string $message): self
    {
        return new self(413, $errorCode, $message);
    }

    public static function badGateway(string $errorCode, string $message): self
    {
        return new self(502, $errorCode, $message);
    }

    public static function tooManyRequests(string $errorCode, string $message): self
    {
        return new self(429, $errorCode, $message);
    }
}

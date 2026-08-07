<?php

namespace App\Service;

use App\Entity\Event;
use App\Exception\ApiException;
use Symfony\Component\HttpFoundation\IpUtils;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class WebhookReplayer
{
    private const TIMEOUT_SECONDS = 10;

    /** Loopback, private, link-local and metadata ranges — blocked as replay targets to prevent SSRF. */
    private const BLOCKED_IP_RANGES = [
        '127.0.0.0/8',
        '10.0.0.0/8',
        '172.16.0.0/12',
        '192.168.0.0/16',
        '169.254.0.0/16',
        '0.0.0.0/8',
        '::1/128',
        'fc00::/7',
        'fe80::/10',
    ];

    /** Hop-by-hop / connection-specific headers that must not be replayed verbatim. */
    private const EXCLUDED_HEADERS = ['host', 'content-length', 'connection', 'transfer-encoding', 'expect'];

    public function __construct(private readonly HttpClientInterface $httpClient)
    {
    }

    public function replay(Event $event, string $targetUrl): array
    {
        $host = parse_url($targetUrl, PHP_URL_HOST);
        $ip = $this->assertUrlSafe($targetUrl, is_string($host) ? $host : '');

        $start = microtime(true);

        try {
            $response = $this->httpClient->request($event->getMethod(), $targetUrl, [
                'headers' => $this->filterHeaders($event->getHeaders()),
                'body' => $event->getBodyRaw() ?? '',
                'timeout' => self::TIMEOUT_SECONDS,
                'max_redirects' => 0,
                // Pin the connection to the IP we already validated below, so DNS
                // cannot resolve differently between the check and the actual request
                // (DNS-rebinding SSRF bypass).
                'resolve' => [$host => $ip],
            ]);

            $statusCode = $response->getStatusCode();
            $responseHeaders = $response->getHeaders(false);
            $responseBody = $response->getContent(false);
        } catch (TransportExceptionInterface $e) {
            throw ApiException::badGateway('replay_failed', 'Could not reach target_url: '.$e->getMessage());
        }

        return [
            'status_code' => $statusCode,
            'headers' => $responseHeaders,
            'body' => $responseBody,
            'duration_ms' => (int) round((microtime(true) - $start) * 1000),
        ];
    }

    /**
     * Resolves and validates the target host, returning the IP that must be used
     * for the actual request (via the HTTP client's `resolve` option) so the
     * connection cannot re-resolve to a different, unvalidated address.
     */
    private function assertUrlSafe(string $url, string $host): string
    {
        $scheme = parse_url($url, PHP_URL_SCHEME) ?? '';

        if (!in_array($scheme, ['http', 'https'], true) || '' === $host) {
            throw ApiException::unprocessable('invalid_target_url', 'target_url must be an absolute http(s) URL');
        }

        $ip = filter_var($host, FILTER_VALIDATE_IP) ? $host : gethostbyname($host);

        if ($ip === $host && !filter_var($host, FILTER_VALIDATE_IP)) {
            throw ApiException::unprocessable('target_url_unresolvable', 'Could not resolve target_url host');
        }

        if (IpUtils::checkIp($ip, self::BLOCKED_IP_RANGES)) {
            throw ApiException::unprocessable('target_url_blocked', 'target_url resolves to a blocked address range');
        }

        return $ip;
    }

    private function filterHeaders(array $headers): array
    {
        $filtered = [];
        foreach ($headers as $name => $values) {
            if (in_array(strtolower((string) $name), self::EXCLUDED_HEADERS, true)) {
                continue;
            }
            $filtered[$name] = implode(', ', (array) $values);
        }

        return $filtered;
    }
}

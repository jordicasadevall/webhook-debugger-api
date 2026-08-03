<?php

namespace App\Tests\Service;

use App\Entity\Event;
use App\Entity\Inbox;
use App\Exception\ApiException;
use App\Service\WebhookReplayer;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\Exception\TransportException;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

class WebhookReplayerTest extends TestCase
{
    private function makeEvent(): Event
    {
        return new Event(
            inbox: new Inbox('test'),
            method: 'POST',
            url: 'http://example.test/in/abc',
            headers: ['content-type' => ['application/json'], 'host' => ['example.test']],
            queryParams: [],
            bodyRaw: '{"a":1}',
            bodyJson: ['a' => 1],
            clientIp: '1.2.3.4',
        );
    }

    public function testReplaySuccess(): void
    {
        $httpClient = new MockHttpClient(new MockResponse('{"ok":true}', [
            'http_code' => 200,
            'response_headers' => ['content-type' => 'application/json'],
        ]));
        $replayer = new WebhookReplayer($httpClient);

        $result = $replayer->replay($this->makeEvent(), 'http://93.184.216.34/webhook');

        self::assertSame(200, $result['status_code']);
        self::assertSame('{"ok":true}', $result['body']);
        self::assertArrayHasKey('duration_ms', $result);
    }

    public function testReplayDoesNotForwardHopByHopHeaders(): void
    {
        $capturedHeaders = null;
        $httpClient = new MockHttpClient(function (string $method, string $url, array $options) use (&$capturedHeaders) {
            $capturedHeaders = $options['headers'] ?? [];

            return new MockResponse('ok');
        });
        $replayer = new WebhookReplayer($httpClient);

        $replayer->replay($this->makeEvent(), 'http://93.184.216.34/webhook');

        $lowercasedHeaderLines = array_map(strtolower(...), $capturedHeaders);
        self::assertFalse(array_any($lowercasedHeaderLines, static fn (string $line) => str_starts_with($line, 'host:')));
        self::assertContains('content-type: application/json', $lowercasedHeaderLines);
    }

    public function testReplayBlocksLoopbackTarget(): void
    {
        $replayer = new WebhookReplayer(new MockHttpClient());

        $this->expectException(ApiException::class);

        $replayer->replay($this->makeEvent(), 'http://127.0.0.1/webhook');
    }

    public function testReplayBlocksPrivateRangeTarget(): void
    {
        $replayer = new WebhookReplayer(new MockHttpClient());

        $this->expectException(ApiException::class);

        $replayer->replay($this->makeEvent(), 'http://192.168.1.1/webhook');
    }

    public function testReplayRejectsNonHttpScheme(): void
    {
        $replayer = new WebhookReplayer(new MockHttpClient());

        $this->expectException(ApiException::class);

        $replayer->replay($this->makeEvent(), 'ftp://example.com/webhook');
    }

    public function testReplayWrapsTransportFailureAs502(): void
    {
        $httpClient = new MockHttpClient(new MockResponse((static function () {
            yield new TransportException('Connection refused');
        })()));
        $replayer = new WebhookReplayer($httpClient);

        try {
            $replayer->replay($this->makeEvent(), 'http://93.184.216.34/webhook');
            self::fail('Expected ApiException to be thrown');
        } catch (ApiException $e) {
            self::assertSame(502, $e->getStatusCode());
        }
    }
}

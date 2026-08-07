<?php

namespace App\Tests\Controller;

use App\Tests\WebhookDebuggerTestCase;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;

class WebhookControllerTest extends WebhookDebuggerTestCase
{
    private function createInbox(KernelBrowser $client): string
    {
        $client->request('POST', '/inboxes', server: ['CONTENT_TYPE' => 'application/json'], content: '{}');

        return json_decode($client->getResponse()->getContent(), true)['id'];
    }

    #[Test]
    public function receiveJsonPostCapturesBodyAndQuery(): void
    {
        $client = static::createClient();
        $this->resetDatabase($client->getContainer()->get(EntityManagerInterface::class));
        $inboxId = $this->createInbox($client);

        $client->request(
            'POST',
            "/in/$inboxId?foo=bar",
            server: ['CONTENT_TYPE' => 'application/json'],
            content: json_encode(['hello' => 'world']),
        );

        self::assertResponseStatusCodeSame(200);
        $data = json_decode($client->getResponse()->getContent(), true);
        self::assertSame('received', $data['status']);
        self::assertArrayHasKey('event_id', $data);
    }

    #[Test]
    public function receiveGetWithoutBody(): void
    {
        $client = static::createClient();
        $this->resetDatabase($client->getContainer()->get(EntityManagerInterface::class));
        $inboxId = $this->createInbox($client);

        $client->request('GET', "/in/$inboxId?ping=1");

        self::assertResponseStatusCodeSame(200);
    }

    #[Test]
    public function receiveUnknownInboxReturns404(): void
    {
        $client = static::createClient();
        $this->resetDatabase($client->getContainer()->get(EntityManagerInterface::class));

        $client->request('POST', '/in/00000000-0000-0000-0000-000000000000');

        self::assertResponseStatusCodeSame(404);
    }

    #[Test]
    public function payloadTooLargeReturns413(): void
    {
        $client = static::createClient();
        $this->resetDatabase($client->getContainer()->get(EntityManagerInterface::class));
        $inboxId = $this->createInbox($client);

        $client->request(
            'POST',
            "/in/$inboxId",
            server: ['CONTENT_TYPE' => 'text/plain'],
            content: str_repeat('x', 1_000_001),
        );

        self::assertResponseStatusCodeSame(413);
    }

    #[Test]
    public function excessiveRequestsAreRateLimited(): void
    {
        $client = static::createClient();
        $client->setServerParameter('REMOTE_ADDR', '203.0.113.10');
        $this->resetDatabase($client->getContainer()->get(EntityManagerInterface::class));
        $inboxId = $this->createInbox($client);

        // test env limiters are configured with limit=3 (see config/packages/framework.yaml)
        for ($i = 0; $i < 3; ++$i) {
            $client->request('POST', "/in/$inboxId");
            self::assertResponseStatusCodeSame(200);
        }

        $client->request('POST', "/in/$inboxId");

        self::assertResponseStatusCodeSame(429);
        $data = json_decode($client->getResponse()->getContent(), true);
        self::assertSame('rate_limited', $data['error']['code']);
    }
}

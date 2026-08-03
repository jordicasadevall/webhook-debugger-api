<?php

namespace App\Tests\Controller;

use App\Tests\WebhookDebuggerTestCase;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;

class WebhookControllerTest extends WebhookDebuggerTestCase
{
    private function createInbox(KernelBrowser $client): string
    {
        $client->request('POST', '/inboxes', server: ['CONTENT_TYPE' => 'application/json'], content: '{}');

        return json_decode($client->getResponse()->getContent(), true)['id'];
    }

    public function testReceiveJsonPostCapturesBodyAndQuery(): void
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

    public function testReceiveGetWithoutBody(): void
    {
        $client = static::createClient();
        $this->resetDatabase($client->getContainer()->get(EntityManagerInterface::class));
        $inboxId = $this->createInbox($client);

        $client->request('GET', "/in/$inboxId?ping=1");

        self::assertResponseStatusCodeSame(200);
    }

    public function testReceiveUnknownInboxReturns404(): void
    {
        $client = static::createClient();
        $this->resetDatabase($client->getContainer()->get(EntityManagerInterface::class));

        $client->request('POST', '/in/00000000-0000-0000-0000-000000000000');

        self::assertResponseStatusCodeSame(404);
    }

    public function testPayloadTooLargeReturns413(): void
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
}

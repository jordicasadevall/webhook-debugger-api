<?php

namespace App\Tests\Controller;

use App\Tests\WebhookDebuggerTestCase;
use Doctrine\ORM\EntityManagerInterface;

class InboxControllerTest extends WebhookDebuggerTestCase
{
    public function testCreateInbox(): void
    {
        $client = static::createClient();
        $this->resetDatabase($client->getContainer()->get(EntityManagerInterface::class));

        $client->request('POST', '/inboxes', server: ['CONTENT_TYPE' => 'application/json'], content: json_encode(['name' => 'my inbox']));

        self::assertResponseStatusCodeSame(201);
        $data = json_decode($client->getResponse()->getContent(), true);
        self::assertArrayHasKey('id', $data);
        self::assertSame('my inbox', $data['name']);
        self::assertStringContainsString('/in/'.$data['id'], $data['url']);
        self::assertArrayHasKey('created_at', $data);
    }

    public function testCreateInboxWithoutName(): void
    {
        $client = static::createClient();
        $this->resetDatabase($client->getContainer()->get(EntityManagerInterface::class));

        $client->request('POST', '/inboxes', server: ['CONTENT_TYPE' => 'application/json'], content: '{}');

        self::assertResponseStatusCodeSame(201);
        $data = json_decode($client->getResponse()->getContent(), true);
        self::assertNull($data['name']);
    }

    public function testShowInbox(): void
    {
        $client = static::createClient();
        $this->resetDatabase($client->getContainer()->get(EntityManagerInterface::class));

        $client->request('POST', '/inboxes', server: ['CONTENT_TYPE' => 'application/json'], content: '{}');
        $created = json_decode($client->getResponse()->getContent(), true);

        $client->request('GET', '/inboxes/'.$created['id']);

        self::assertResponseIsSuccessful();
        $data = json_decode($client->getResponse()->getContent(), true);
        self::assertSame($created['id'], $data['id']);
    }

    public function testShowUnknownInboxReturns404(): void
    {
        $client = static::createClient();
        $this->resetDatabase($client->getContainer()->get(EntityManagerInterface::class));

        $client->request('GET', '/inboxes/00000000-0000-0000-0000-000000000000');

        self::assertResponseStatusCodeSame(404);
        $data = json_decode($client->getResponse()->getContent(), true);
        self::assertSame('not_found', $data['error']['code']);
    }
}

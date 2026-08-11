<?php

namespace App\Tests\Controller;

use App\Tests\WebhookDebuggerTestCase;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;

class EventControllerTest extends WebhookDebuggerTestCase
{
    private function createInboxWithEvent(KernelBrowser $client): array
    {
        $client->request('POST', '/inboxes', server: ['CONTENT_TYPE' => 'application/json'], content: '{}');
        $inboxId = json_decode($client->getResponse()->getContent(), true)['id'];

        $client->request(
            'POST',
            "/in/$inboxId",
            server: ['CONTENT_TYPE' => 'application/json'],
            content: json_encode(['a' => 1]),
        );
        $eventId = json_decode($client->getResponse()->getContent(), true)['event_id'];

        return [$inboxId, $eventId];
    }

    #[Test]
    public function listEvents(): void
    {
        $client = static::createClient();
        $this->resetDatabase($client->getContainer()->get(EntityManagerInterface::class));
        [$inboxId] = $this->createInboxWithEvent($client);

        $client->request('GET', "/inboxes/$inboxId/events");

        self::assertResponseIsSuccessful();
        $data = json_decode($client->getResponse()->getContent(), true);
        self::assertSame(1, $data['total']);
        self::assertCount(1, $data['events']);
        self::assertArrayNotHasKey('headers', $data['events'][0]);
    }

    #[Test]
    public function showEvent(): void
    {
        $client = static::createClient();
        $this->resetDatabase($client->getContainer()->get(EntityManagerInterface::class));
        [$inboxId, $eventId] = $this->createInboxWithEvent($client);

        $client->request('GET', "/inboxes/$inboxId/events/$eventId");

        self::assertResponseIsSuccessful();
        $data = json_decode($client->getResponse()->getContent(), true);
        self::assertSame($eventId, $data['id']);
        self::assertSame(['a' => 1], $data['body_json']);
        self::assertArrayHasKey('headers', $data);
    }

    #[Test]
    public function showUnknownEventReturns404(): void
    {
        $client = static::createClient();
        $this->resetDatabase($client->getContainer()->get(EntityManagerInterface::class));
        [$inboxId] = $this->createInboxWithEvent($client);

        $client->request('GET', "/inboxes/$inboxId/events/00000000-0000-0000-0000-000000000000");

        self::assertResponseStatusCodeSame(404);
    }

    #[Test]
    public function showEventFromWrongInboxReturns404(): void
    {
        $client = static::createClient();
        $this->resetDatabase($client->getContainer()->get(EntityManagerInterface::class));
        [, $eventId] = $this->createInboxWithEvent($client);

        $client->request('POST', '/inboxes', server: ['CONTENT_TYPE' => 'application/json'], content: '{}');
        $otherInboxId = json_decode($client->getResponse()->getContent(), true)['id'];

        $client->request('GET', "/inboxes/$otherInboxId/events/$eventId");

        self::assertResponseStatusCodeSame(404);
    }

    #[Test]
    public function deleteEvent(): void
    {
        $client = static::createClient();
        $this->resetDatabase($client->getContainer()->get(EntityManagerInterface::class));
        [$inboxId, $eventId] = $this->createInboxWithEvent($client);

        $client->request('DELETE', "/inboxes/$inboxId/events/$eventId");

        self::assertResponseStatusCodeSame(204);

        $client->request('GET', "/inboxes/$inboxId/events/$eventId");
        self::assertResponseStatusCodeSame(404);
    }

    #[Test]
    public function deleteUnknownEventReturns404(): void
    {
        $client = static::createClient();
        $this->resetDatabase($client->getContainer()->get(EntityManagerInterface::class));
        [$inboxId] = $this->createInboxWithEvent($client);

        $client->request('DELETE', "/inboxes/$inboxId/events/00000000-0000-0000-0000-000000000000");

        self::assertResponseStatusCodeSame(404);
    }

    #[Test]
    public function deleteEventFromWrongInboxReturns404(): void
    {
        $client = static::createClient();
        $this->resetDatabase($client->getContainer()->get(EntityManagerInterface::class));
        [$inboxId, $eventId] = $this->createInboxWithEvent($client);

        $client->request('POST', '/inboxes', server: ['CONTENT_TYPE' => 'application/json'], content: '{}');
        $otherInboxId = json_decode($client->getResponse()->getContent(), true)['id'];

        $client->request('DELETE', "/inboxes/$otherInboxId/events/$eventId");
        self::assertResponseStatusCodeSame(404);

        // must not have been deleted via the wrong inbox — still reachable via the real one
        $client->request('GET', "/inboxes/$inboxId/events/$eventId");
        self::assertResponseIsSuccessful();
    }

    #[Test]
    public function replayInvalidUrlReturns422(): void
    {
        $client = static::createClient();
        $this->resetDatabase($client->getContainer()->get(EntityManagerInterface::class));
        [$inboxId, $eventId] = $this->createInboxWithEvent($client);

        $client->request(
            'POST',
            "/inboxes/$inboxId/events/$eventId/replay",
            server: ['CONTENT_TYPE' => 'application/json'],
            content: json_encode(['target_url' => 'not-a-url']),
        );

        self::assertResponseStatusCodeSame(422);
        $data = json_decode($client->getResponse()->getContent(), true);
        self::assertSame('invalid_target_url', $data['error']['code']);
    }

    #[Test]
    public function replayWithNonObjectJsonBodyReturns422(): void
    {
        $client = static::createClient();
        $this->resetDatabase($client->getContainer()->get(EntityManagerInterface::class));
        [$inboxId, $eventId] = $this->createInboxWithEvent($client);

        $client->request(
            'POST',
            "/inboxes/$inboxId/events/$eventId/replay",
            server: ['CONTENT_TYPE' => 'application/json'],
            content: '5',
        );

        self::assertResponseStatusCodeSame(422);
        $data = json_decode($client->getResponse()->getContent(), true);
        self::assertSame('invalid_target_url', $data['error']['code']);
    }

    #[Test]
    public function replayBlocksPrivateTarget(): void
    {
        $client = static::createClient();
        $this->resetDatabase($client->getContainer()->get(EntityManagerInterface::class));
        [$inboxId, $eventId] = $this->createInboxWithEvent($client);

        $client->request(
            'POST',
            "/inboxes/$inboxId/events/$eventId/replay",
            server: ['CONTENT_TYPE' => 'application/json'],
            content: json_encode(['target_url' => 'http://127.0.0.1:1234']),
        );

        self::assertResponseStatusCodeSame(422);
        $data = json_decode($client->getResponse()->getContent(), true);
        self::assertSame('target_url_blocked', $data['error']['code']);
    }

    #[Test]
    public function excessiveReplayRequestsAreRateLimited(): void
    {
        $client = static::createClient();
        $client->setServerParameter('REMOTE_ADDR', '203.0.113.20');
        $this->resetDatabase($client->getContainer()->get(EntityManagerInterface::class));
        [$inboxId, $eventId] = $this->createInboxWithEvent($client);

        $body = json_encode(['target_url' => 'not-a-url']);

        // test env limiter is configured with limit=3 (see config/packages/framework.yaml);
        // the limiter must trip before target_url is even validated.
        for ($i = 0; $i < 3; ++$i) {
            $client->request(
                'POST',
                "/inboxes/$inboxId/events/$eventId/replay",
                server: ['CONTENT_TYPE' => 'application/json'],
                content: $body,
            );
            self::assertResponseStatusCodeSame(422);
        }

        $client->request(
            'POST',
            "/inboxes/$inboxId/events/$eventId/replay",
            server: ['CONTENT_TYPE' => 'application/json'],
            content: $body,
        );

        self::assertResponseStatusCodeSame(429);
        $data = json_decode($client->getResponse()->getContent(), true);
        self::assertSame('rate_limited', $data['error']['code']);
    }
}

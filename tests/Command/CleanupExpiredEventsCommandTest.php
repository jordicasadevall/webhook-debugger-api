<?php

namespace App\Tests\Command;

use App\Command\CleanupExpiredEventsCommand;
use App\Entity\Event;
use App\Entity\Inbox;
use App\Tests\WebhookDebuggerTestCase;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\Console\Tester\CommandTester;

class CleanupExpiredEventsCommandTest extends WebhookDebuggerTestCase
{
    private function makeEvent(Inbox $inbox): Event
    {
        return new Event(
            inbox: $inbox,
            method: 'POST',
            url: 'http://example.test/in/abc',
            headers: [],
            queryParams: [],
            bodyRaw: null,
            bodyJson: null,
            clientIp: null,
        );
    }

    #[Test]
    public function deletesOnlyEventsOlderThanTheRetentionWindow(): void
    {
        self::bootKernel();
        $em = self::getContainer()->get(EntityManagerInterface::class);
        $this->resetDatabase($em);

        $inbox = new Inbox('test');
        $old = $this->makeEvent($inbox);
        $recent = $this->makeEvent($inbox);
        $em->persist($inbox);
        $em->persist($old);
        $em->persist($recent);
        $em->flush();

        // Event::$receivedAt has no setter (always "now" at construction), so
        // backdate it directly for the event that should be purged.
        $em->getConnection()->executeStatement(
            'UPDATE event SET received_at = :cutoff WHERE id = :id',
            ['cutoff' => (new \DateTimeImmutable('-10 days'))->format('Y-m-d H:i:s'), 'id' => (string) $old->getId()],
            ['cutoff' => 'string', 'id' => 'string'],
        );
        $em->clear();

        $tester = new CommandTester(self::getContainer()->get(CleanupExpiredEventsCommand::class));
        $exitCode = $tester->execute([]);

        self::assertSame(0, $exitCode);
        self::assertStringContainsString('Deleted 1 event', $tester->getDisplay());

        $remaining = $em->getConnection()->fetchFirstColumn('SELECT id FROM event');
        self::assertSame([(string) $recent->getId()], $remaining);

        $inboxStillExists = $em->getConnection()->fetchOne('SELECT COUNT(*) FROM inbox WHERE id = :id', ['id' => (string) $inbox->getId()]);
        self::assertSame(1, (int) $inboxStillExists);
    }
}

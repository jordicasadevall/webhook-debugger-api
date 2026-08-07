<?php

namespace App\Command;

use App\Entity\Event;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

#[AsCommand(
    name: 'app:events:cleanup',
    description: 'Delete captured events older than EVENT_RETENTION_DAYS. Inboxes are kept.',
)]
class CleanupExpiredEventsCommand extends Command
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        #[Autowire('%env(int:EVENT_RETENTION_DAYS)%')]
        private readonly int $retentionDays,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        if ($this->retentionDays <= 0) {
            $io->error('EVENT_RETENTION_DAYS must be a positive number of days.');

            return Command::FAILURE;
        }

        $cutoff = new \DateTimeImmutable(sprintf('-%d days', $this->retentionDays));

        $deleted = $this->em->createQuery('DELETE FROM '.Event::class.' e WHERE e.receivedAt < :cutoff')
            ->setParameter('cutoff', $cutoff)
            ->execute();

        $io->success(sprintf('Deleted %d event(s) received before %s.', $deleted, $cutoff->format(DATE_ATOM)));

        return Command::SUCCESS;
    }
}

<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260807220655 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Replace single-column received_at index with a composite (inbox_id, received_at) index matching the actual event-listing query';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP INDEX idx_event_received_at');
        $this->addSql('CREATE INDEX idx_event_inbox_received_at ON event (inbox_id, received_at)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP INDEX idx_event_inbox_received_at');
        $this->addSql('CREATE INDEX idx_event_received_at ON event (received_at)');
    }
}

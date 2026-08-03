<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260803215906 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE event (id UUID NOT NULL, method VARCHAR(10) NOT NULL, url TEXT NOT NULL, headers JSON NOT NULL, query_params JSON NOT NULL, body_raw TEXT DEFAULT NULL, body_json JSON DEFAULT NULL, client_ip VARCHAR(45) DEFAULT NULL, received_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, inbox_id UUID NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX IDX_3BAE0AA718DA89DD ON event (inbox_id)');
        $this->addSql('CREATE INDEX idx_event_received_at ON event (received_at)');
        $this->addSql('CREATE TABLE inbox (id UUID NOT NULL, name VARCHAR(255) DEFAULT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY (id))');
        $this->addSql('ALTER TABLE event ADD CONSTRAINT FK_3BAE0AA718DA89DD FOREIGN KEY (inbox_id) REFERENCES inbox (id) ON DELETE CASCADE NOT DEFERRABLE');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE event DROP CONSTRAINT FK_3BAE0AA718DA89DD');
        $this->addSql('DROP TABLE event');
        $this->addSql('DROP TABLE inbox');
    }
}

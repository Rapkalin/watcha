<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260624120646 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add notified_at to site_alert (existing alerts marked as already notified).';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE site_alert ADD notified_at DATETIME DEFAULT NULL');
        // Existing alerts predate e-mail notifications — mark them as already notified so the
        // first scan after deploy does not blast a backlog digest to owners.
        $this->addSql('UPDATE site_alert SET notified_at = NOW()');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE site_alert DROP notified_at');
    }
}

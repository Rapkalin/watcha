<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260624110756 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add e-mail verification status to user (existing accounts kept as verified).';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE `user` ADD email_verified TINYINT NOT NULL, ADD email_verified_at DATETIME DEFAULT NULL');
        // Existing accounts predate e-mail verification — treat them as already verified so they
        // are not locked out once login requires a verified e-mail.
        $this->addSql('UPDATE `user` SET email_verified = 1, email_verified_at = NOW()');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE `user` DROP email_verified, DROP email_verified_at');
    }
}

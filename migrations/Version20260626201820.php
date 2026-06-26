<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260626201820 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add page_scan and page_result tables for the site page-availability checker.';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE page_result (id INT AUTO_INCREMENT NOT NULL, url VARCHAR(1024) NOT NULL, status_code INT DEFAULT NULL, page_scan_id INT NOT NULL, INDEX IDX_7CBC370DD2474146 (page_scan_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE page_scan (id INT AUTO_INCREMENT NOT NULL, scanned_at DATETIME NOT NULL, note VARCHAR(255) DEFAULT NULL, total_pages INT NOT NULL, ok_count INT NOT NULL, error_count INT NOT NULL, site_id INT NOT NULL, INDEX IDX_4377AF74F6BD1646 (site_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE page_result ADD CONSTRAINT FK_7CBC370DD2474146 FOREIGN KEY (page_scan_id) REFERENCES page_scan (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE page_scan ADD CONSTRAINT FK_4377AF74F6BD1646 FOREIGN KEY (site_id) REFERENCES site (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE page_result DROP FOREIGN KEY FK_7CBC370DD2474146');
        $this->addSql('ALTER TABLE page_scan DROP FOREIGN KEY FK_4377AF74F6BD1646');
        $this->addSql('DROP TABLE page_result');
        $this->addSql('DROP TABLE page_scan');
    }
}

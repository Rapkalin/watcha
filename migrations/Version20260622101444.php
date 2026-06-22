<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260622101444 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE advisory (id INT AUTO_INCREMENT NOT NULL, technology VARCHAR(20) NOT NULL, source VARCHAR(40) NOT NULL, external_id VARCHAR(100) NOT NULL, cve_id VARCHAR(50) DEFAULT NULL, title VARCHAR(255) NOT NULL, summary LONGTEXT DEFAULT NULL, severity VARCHAR(20) NOT NULL, affected_constraint VARCHAR(255) DEFAULT NULL, fixed_version VARCHAR(50) DEFAULT NULL, reference_url VARCHAR(1024) DEFAULT NULL, published_at DATETIME DEFAULT NULL, imported_at DATETIME NOT NULL, INDEX idx_technology (technology), UNIQUE INDEX uniq_source_external (source, external_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE site (id INT AUTO_INCREMENT NOT NULL, name VARCHAR(120) NOT NULL, url VARCHAR(512) NOT NULL, technology VARCHAR(20) DEFAULT NULL, detected_version VARCHAR(50) DEFAULT NULL, latest_known_version VARCHAR(50) DEFAULT NULL, last_scanned_at DATETIME DEFAULT NULL, last_scan_message VARCHAR(255) DEFAULT NULL, created_at DATETIME NOT NULL, owner_id INT NOT NULL, INDEX IDX_694309E47E3C61F9 (owner_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE site_alert (id INT AUTO_INCREMENT NOT NULL, type VARCHAR(30) NOT NULL, dedup_key VARCHAR(100) NOT NULL, severity VARCHAR(20) NOT NULL, message VARCHAR(255) NOT NULL, resolved TINYINT NOT NULL, acknowledged TINYINT NOT NULL, created_at DATETIME NOT NULL, resolved_at DATETIME DEFAULT NULL, site_id INT NOT NULL, advisory_id INT DEFAULT NULL, INDEX IDX_D3A796C4F6BD1646 (site_id), INDEX IDX_D3A796C446CB6A73 (advisory_id), UNIQUE INDEX uniq_alert_dedup (site_id, type, dedup_key), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE `user` (id INT AUTO_INCREMENT NOT NULL, email VARCHAR(180) NOT NULL, roles JSON NOT NULL, password VARCHAR(255) NOT NULL, display_name VARCHAR(120) DEFAULT NULL, approved TINYINT NOT NULL, approved_at DATETIME DEFAULT NULL, created_at DATETIME NOT NULL, approved_by_id INT DEFAULT NULL, UNIQUE INDEX UNIQ_8D93D649E7927C74 (email), INDEX IDX_8D93D6492D234F6A (approved_by_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE site ADD CONSTRAINT FK_694309E47E3C61F9 FOREIGN KEY (owner_id) REFERENCES `user` (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE site_alert ADD CONSTRAINT FK_D3A796C4F6BD1646 FOREIGN KEY (site_id) REFERENCES site (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE site_alert ADD CONSTRAINT FK_D3A796C446CB6A73 FOREIGN KEY (advisory_id) REFERENCES advisory (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE `user` ADD CONSTRAINT FK_8D93D6492D234F6A FOREIGN KEY (approved_by_id) REFERENCES `user` (id) ON DELETE SET NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE site DROP FOREIGN KEY FK_694309E47E3C61F9');
        $this->addSql('ALTER TABLE site_alert DROP FOREIGN KEY FK_D3A796C4F6BD1646');
        $this->addSql('ALTER TABLE site_alert DROP FOREIGN KEY FK_D3A796C446CB6A73');
        $this->addSql('ALTER TABLE `user` DROP FOREIGN KEY FK_8D93D6492D234F6A');
        $this->addSql('DROP TABLE advisory');
        $this->addSql('DROP TABLE site');
        $this->addSql('DROP TABLE site_alert');
        $this->addSql('DROP TABLE `user`');
    }
}

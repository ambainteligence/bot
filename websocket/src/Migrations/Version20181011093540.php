<?php declare(strict_types = 1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Migrations\AbstractMigration;
use Doctrine\DBAL\Schema\Schema;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
class Version20181011093540 extends AbstractMigration
{
    public function up(Schema $schema)
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('DROP TABLE back_activity');
        $this->addSql('DROP TABLE back_profit_month');
        $this->addSql('ALTER TABLE activity CHANGE trade_id trade_id VARCHAR(255) NOT NULL');
        $this->addSql('ALTER TABLE profit ADD data LONGTEXT DEFAULT NULL');
    }

    public function down(Schema $schema)
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('CREATE TABLE back_activity (id INT AUTO_INCREMENT NOT NULL, uuid VARCHAR(255) DEFAULT NULL COLLATE utf8mb4_unicode_ci, uid INT DEFAULT NULL, outcome VARCHAR(255) NOT NULL COLLATE utf8mb4_unicode_ci, data VARCHAR(255) DEFAULT NULL COLLATE utf8mb4_unicode_ci, class VARCHAR(255) DEFAULT NULL COLLATE utf8mb4_unicode_ci, exchange VARCHAR(255) DEFAULT NULL COLLATE utf8mb4_unicode_ci, PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8 COLLATE utf8_unicode_ci ENGINE = InnoDB');
        $this->addSql('CREATE TABLE back_profit_month (id INT AUTO_INCREMENT NOT NULL, uid VARCHAR(255) NOT NULL COLLATE utf8mb4_unicode_ci, exchange VARCHAR(255) DEFAULT NULL COLLATE utf8mb4_unicode_ci, created_date VARCHAR(255) DEFAULT NULL COLLATE utf8mb4_unicode_ci, percent VARCHAR(255) NOT NULL COLLATE utf8mb4_unicode_ci, data LONGTEXT DEFAULT NULL COLLATE utf8mb4_unicode_ci, PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8 COLLATE utf8_unicode_ci ENGINE = InnoDB');
        $this->addSql('ALTER TABLE activity CHANGE trade_id trade_id VARCHAR(255) DEFAULT NULL COLLATE utf8mb4_unicode_ci');
        $this->addSql('ALTER TABLE profit DROP data');
    }
}

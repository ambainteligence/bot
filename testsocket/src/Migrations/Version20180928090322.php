<?php declare(strict_types = 1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Migrations\AbstractMigration;
use Doctrine\DBAL\Schema\Schema;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
class Version20180928090322 extends AbstractMigration
{
    public function up(Schema $schema)
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('CREATE TABLE back_activity (id INT AUTO_INCREMENT NOT NULL, uuid VARCHAR(255) DEFAULT NULL, uid INT DEFAULT NULL, outcome VARCHAR(255) NOT NULL, data VARCHAR(255) DEFAULT NULL, class VARCHAR(255) DEFAULT NULL, exchange VARCHAR(255) DEFAULT NULL, PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB');
        $this->addSql('ALTER TABLE activity CHANGE trade_id trade_id VARCHAR(255) NOT NULL');
    }

    public function down(Schema $schema)
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('DROP TABLE back_activity');
        $this->addSql('ALTER TABLE activity CHANGE trade_id trade_id VARCHAR(255) DEFAULT NULL COLLATE utf8mb4_unicode_ci');
    }
}

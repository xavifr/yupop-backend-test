<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20230303235329 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE frame DROP roll_bonus');
        $this->addSql('ALTER TABLE game ADD winner_player_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE game ADD CONSTRAINT FK_232B318C6874926C FOREIGN KEY (winner_player_id) REFERENCES player (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_232B318C6874926C ON game (winner_player_id)');
        $this->addSql('ALTER TABLE player ADD last_round INT DEFAULT 0 NOT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE SCHEMA public');
        $this->addSql('ALTER TABLE player DROP last_round');
        $this->addSql('ALTER TABLE game DROP CONSTRAINT FK_232B318C6874926C');
        $this->addSql('DROP INDEX UNIQ_232B318C6874926C');
        $this->addSql('ALTER TABLE game DROP winner_player_id');
        $this->addSql('ALTER TABLE frame ADD roll_bonus INT DEFAULT 0 NOT NULL');
    }
}

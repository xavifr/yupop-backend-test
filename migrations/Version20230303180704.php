<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20230303180704 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE SEQUENCE frame_id_seq INCREMENT BY 1 MINVALUE 1 START 1');
        $this->addSql('CREATE SEQUENCE game_id_seq INCREMENT BY 1 MINVALUE 1 START 1');
        $this->addSql('CREATE SEQUENCE player_id_seq INCREMENT BY 1 MINVALUE 1 START 1');
        $this->addSql('CREATE SEQUENCE test_id_seq INCREMENT BY 1 MINVALUE 1 START 1');
        $this->addSql('CREATE TABLE frame (id INT NOT NULL, player_id INT NOT NULL, round INT NOT NULL, state VARCHAR(255) DEFAULT \'new\' NOT NULL, score_wait INT DEFAULT 0 NOT NULL, roll_1 INT DEFAULT 0 NOT NULL, roll_2 INT DEFAULT 0 NOT NULL, roll_bonus INT DEFAULT 0 NOT NULL, score INT DEFAULT 0 NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX IDX_B5F83CCD99E6F5DF ON frame (player_id)');
        $this->addSql('CREATE UNIQUE INDEX player_round_idx ON frame (player_id, round)');
        $this->addSql('CREATE TABLE game (id INT NOT NULL, name VARCHAR(255) NOT NULL, reference VARCHAR(255) NOT NULL, state VARCHAR(255) DEFAULT \'new\' NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE TABLE player (id INT NOT NULL, game_id INT NOT NULL, name VARCHAR(255) NOT NULL, state VARCHAR(255) DEFAULT \'new\' NOT NULL, final_score INT DEFAULT 0 NOT NULL, position INT NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX IDX_98197A65E48FD905 ON player (game_id)');
        $this->addSql('CREATE UNIQUE INDEX game_position_idx ON player (game_id, position)');
        $this->addSql('CREATE TABLE test (id INT NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE TABLE messenger_messages (id BIGSERIAL NOT NULL, body TEXT NOT NULL, headers TEXT NOT NULL, queue_name VARCHAR(190) NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, available_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, delivered_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX IDX_75EA56E0FB7336F0 ON messenger_messages (queue_name)');
        $this->addSql('CREATE INDEX IDX_75EA56E0E3BD61CE ON messenger_messages (available_at)');
        $this->addSql('CREATE INDEX IDX_75EA56E016BA31DB ON messenger_messages (delivered_at)');
        $this->addSql('CREATE OR REPLACE FUNCTION notify_messenger_messages() RETURNS TRIGGER AS $$
            BEGIN
                PERFORM pg_notify(\'messenger_messages\', NEW.queue_name::text);
                RETURN NEW;
            END;
        $$ LANGUAGE plpgsql;');
        $this->addSql('DROP TRIGGER IF EXISTS notify_trigger ON messenger_messages;');
        $this->addSql('CREATE TRIGGER notify_trigger AFTER INSERT OR UPDATE ON messenger_messages FOR EACH ROW EXECUTE PROCEDURE notify_messenger_messages();');
        $this->addSql('ALTER TABLE frame ADD CONSTRAINT FK_B5F83CCD99E6F5DF FOREIGN KEY (player_id) REFERENCES player (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE player ADD CONSTRAINT FK_98197A65E48FD905 FOREIGN KEY (game_id) REFERENCES game (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE SCHEMA public');
        $this->addSql('DROP SEQUENCE frame_id_seq CASCADE');
        $this->addSql('DROP SEQUENCE game_id_seq CASCADE');
        $this->addSql('DROP SEQUENCE player_id_seq CASCADE');
        $this->addSql('DROP SEQUENCE test_id_seq CASCADE');
        $this->addSql('ALTER TABLE frame DROP CONSTRAINT FK_B5F83CCD99E6F5DF');
        $this->addSql('ALTER TABLE player DROP CONSTRAINT FK_98197A65E48FD905');
        $this->addSql('DROP TABLE frame');
        $this->addSql('DROP TABLE game');
        $this->addSql('DROP TABLE player');
        $this->addSql('DROP TABLE test');
        $this->addSql('DROP TABLE messenger_messages');
    }
}

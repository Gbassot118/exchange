<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260111215124 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE estimation_votes (id UUID NOT NULL, value VARCHAR(10) NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, estimation_id UUID NOT NULL, participant_id UUID NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX IDX_52D1C3A5F35F62F2 ON estimation_votes (estimation_id)');
        $this->addSql('CREATE INDEX IDX_52D1C3A59D1C3019 ON estimation_votes (participant_id)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_52D1C3A5F35F62F29D1C3019 ON estimation_votes (estimation_id, participant_id)');
        $this->addSql('CREATE TABLE estimations (id UUID NOT NULL, title VARCHAR(255) NOT NULL, description TEXT DEFAULT NULL, status VARCHAR(50) NOT NULL, revealed_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, session_id UUID NOT NULL, linked_document_id UUID DEFAULT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX IDX_27DD79AA613FECDF ON estimations (session_id)');
        $this->addSql('CREATE INDEX IDX_27DD79AA2B1068DF ON estimations (linked_document_id)');
        $this->addSql('ALTER TABLE estimation_votes ADD CONSTRAINT FK_52D1C3A5F35F62F2 FOREIGN KEY (estimation_id) REFERENCES estimations (id) ON DELETE CASCADE NOT DEFERRABLE');
        $this->addSql('ALTER TABLE estimation_votes ADD CONSTRAINT FK_52D1C3A59D1C3019 FOREIGN KEY (participant_id) REFERENCES participants (id) ON DELETE CASCADE NOT DEFERRABLE');
        $this->addSql('ALTER TABLE estimations ADD CONSTRAINT FK_27DD79AA613FECDF FOREIGN KEY (session_id) REFERENCES sessions (id) ON DELETE CASCADE NOT DEFERRABLE');
        $this->addSql('ALTER TABLE estimations ADD CONSTRAINT FK_27DD79AA2B1068DF FOREIGN KEY (linked_document_id) REFERENCES documents (id) ON DELETE SET NULL NOT DEFERRABLE');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE estimation_votes DROP CONSTRAINT FK_52D1C3A5F35F62F2');
        $this->addSql('ALTER TABLE estimation_votes DROP CONSTRAINT FK_52D1C3A59D1C3019');
        $this->addSql('ALTER TABLE estimations DROP CONSTRAINT FK_27DD79AA613FECDF');
        $this->addSql('ALTER TABLE estimations DROP CONSTRAINT FK_27DD79AA2B1068DF');
        $this->addSql('DROP TABLE estimation_votes');
        $this->addSql('DROP TABLE estimations');
    }
}

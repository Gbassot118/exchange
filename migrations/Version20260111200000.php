<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Migration for Estimation (Planning Poker) feature
 */
final class Version20260111200000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add estimations and estimation_votes tables for Planning Poker feature';
    }

    public function up(Schema $schema): void
    {
        // Create estimations table
        $this->addSql('CREATE TABLE estimations (
            id UUID NOT NULL,
            session_id UUID NOT NULL,
            linked_document_id UUID DEFAULT NULL,
            title VARCHAR(255) NOT NULL,
            description TEXT DEFAULT NULL,
            status VARCHAR(50) NOT NULL DEFAULT \'open\',
            revealed_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL,
            created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
            updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
            PRIMARY KEY(id)
        )');
        $this->addSql('CREATE INDEX IDX_estimations_session_id ON estimations (session_id)');
        $this->addSql('CREATE INDEX IDX_estimations_linked_document_id ON estimations (linked_document_id)');
        $this->addSql('COMMENT ON COLUMN estimations.id IS \'(DC2Type:uuid)\'');
        $this->addSql('COMMENT ON COLUMN estimations.session_id IS \'(DC2Type:uuid)\'');
        $this->addSql('COMMENT ON COLUMN estimations.linked_document_id IS \'(DC2Type:uuid)\'');
        $this->addSql('COMMENT ON COLUMN estimations.created_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN estimations.updated_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN estimations.revealed_at IS \'(DC2Type:datetime_immutable)\'');

        // Create estimation_votes table
        $this->addSql('CREATE TABLE estimation_votes (
            id UUID NOT NULL,
            estimation_id UUID NOT NULL,
            participant_id UUID NOT NULL,
            value VARCHAR(10) NOT NULL,
            created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
            PRIMARY KEY(id)
        )');
        $this->addSql('CREATE INDEX IDX_estimation_votes_estimation_id ON estimation_votes (estimation_id)');
        $this->addSql('CREATE INDEX IDX_estimation_votes_participant_id ON estimation_votes (participant_id)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_estimation_votes_estimation_participant ON estimation_votes (estimation_id, participant_id)');
        $this->addSql('COMMENT ON COLUMN estimation_votes.id IS \'(DC2Type:uuid)\'');
        $this->addSql('COMMENT ON COLUMN estimation_votes.estimation_id IS \'(DC2Type:uuid)\'');
        $this->addSql('COMMENT ON COLUMN estimation_votes.participant_id IS \'(DC2Type:uuid)\'');
        $this->addSql('COMMENT ON COLUMN estimation_votes.created_at IS \'(DC2Type:datetime_immutable)\'');

        // Add foreign key constraints
        $this->addSql('ALTER TABLE estimations ADD CONSTRAINT FK_estimations_session_id FOREIGN KEY (session_id) REFERENCES sessions (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE estimations ADD CONSTRAINT FK_estimations_linked_document_id FOREIGN KEY (linked_document_id) REFERENCES documents (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE estimation_votes ADD CONSTRAINT FK_estimation_votes_estimation_id FOREIGN KEY (estimation_id) REFERENCES estimations (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE estimation_votes ADD CONSTRAINT FK_estimation_votes_participant_id FOREIGN KEY (participant_id) REFERENCES participants (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
    }

    public function down(Schema $schema): void
    {
        // Drop foreign key constraints
        $this->addSql('ALTER TABLE estimation_votes DROP CONSTRAINT FK_estimation_votes_estimation_id');
        $this->addSql('ALTER TABLE estimation_votes DROP CONSTRAINT FK_estimation_votes_participant_id');
        $this->addSql('ALTER TABLE estimations DROP CONSTRAINT FK_estimations_session_id');
        $this->addSql('ALTER TABLE estimations DROP CONSTRAINT FK_estimations_linked_document_id');

        // Drop tables
        $this->addSql('DROP TABLE estimation_votes');
        $this->addSql('DROP TABLE estimations');
    }
}

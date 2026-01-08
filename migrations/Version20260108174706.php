<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260108174706 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE annotations (id UUID NOT NULL, content TEXT NOT NULL, type VARCHAR(50) NOT NULL, status VARCHAR(50) NOT NULL, anchor JSON DEFAULT NULL, mentions JSON DEFAULT NULL, taken_into_account BOOLEAN NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, document_id UUID NOT NULL, author_id UUID NOT NULL, parent_annotation_id UUID DEFAULT NULL, resolved_by_id UUID DEFAULT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX IDX_48931805C33F7837 ON annotations (document_id)');
        $this->addSql('CREATE INDEX IDX_48931805F675F31B ON annotations (author_id)');
        $this->addSql('CREATE INDEX IDX_489318053CF39FC6 ON annotations (parent_annotation_id)');
        $this->addSql('CREATE INDEX IDX_489318056713A32B ON annotations (resolved_by_id)');
        $this->addSql('CREATE INDEX IDX_489318057B00651C8CDE5729 ON annotations (status, type)');
        $this->addSql('CREATE INDEX IDX_48931805C33F78377B00651C ON annotations (document_id, status)');
        $this->addSql('CREATE TABLE decisions (id UUID NOT NULL, title VARCHAR(255) NOT NULL, description TEXT DEFAULT NULL, status VARCHAR(50) NOT NULL, options JSON NOT NULL, selected_option_id UUID DEFAULT NULL, is_locked BOOLEAN NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, session_id UUID NOT NULL, linked_document_id UUID DEFAULT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX IDX_638DAA17613FECDF ON decisions (session_id)');
        $this->addSql('CREATE INDEX IDX_638DAA172B1068DF ON decisions (linked_document_id)');
        $this->addSql('CREATE TABLE document_versions (id UUID NOT NULL, version INT NOT NULL, content TEXT NOT NULL, metadata JSON DEFAULT NULL, change_description VARCHAR(255) DEFAULT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, document_id UUID NOT NULL, author_id UUID DEFAULT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX IDX_961DB18BC33F7837 ON document_versions (document_id)');
        $this->addSql('CREATE INDEX IDX_961DB18BF675F31B ON document_versions (author_id)');
        $this->addSql('CREATE INDEX IDX_961DB18BC33F7837BF1CD3C3 ON document_versions (document_id, version)');
        $this->addSql('CREATE TABLE documents (id UUID NOT NULL, title VARCHAR(255) NOT NULL, slug VARCHAR(255) NOT NULL, content TEXT NOT NULL, type VARCHAR(50) NOT NULL, metadata JSON DEFAULT NULL, sort_order INT NOT NULL, current_version INT NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, session_id UUID NOT NULL, parent_id UUID DEFAULT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX IDX_A2B07288613FECDF ON documents (session_id)');
        $this->addSql('CREATE INDEX IDX_A2B07288727ACA70 ON documents (parent_id)');
        $this->addSql('CREATE INDEX IDX_A2B07288613FECDF989D9B62 ON documents (session_id, slug)');
        $this->addSql('CREATE TABLE participants (id UUID NOT NULL, pseudo VARCHAR(100) NOT NULL, color VARCHAR(7) NOT NULL, last_seen_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, current_document_id UUID DEFAULT NULL, is_agent BOOLEAN NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, session_id UUID NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX IDX_71697092613FECDF ON participants (session_id)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_71697092613FECDF86CC499D ON participants (session_id, pseudo)');
        $this->addSql('CREATE TABLE sessions (id UUID NOT NULL, title VARCHAR(255) NOT NULL, description TEXT DEFAULT NULL, status VARCHAR(50) NOT NULL, invite_code VARCHAR(64) NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_9A609D136F21F112 ON sessions (invite_code)');
        $this->addSql('CREATE TABLE votes (id UUID NOT NULL, option_id UUID NOT NULL, comment TEXT DEFAULT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, decision_id UUID NOT NULL, participant_id UUID NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX IDX_518B7ACFBDEE7539 ON votes (decision_id)');
        $this->addSql('CREATE INDEX IDX_518B7ACF9D1C3019 ON votes (participant_id)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_518B7ACFBDEE75399D1C3019 ON votes (decision_id, participant_id)');
        $this->addSql('ALTER TABLE annotations ADD CONSTRAINT FK_48931805C33F7837 FOREIGN KEY (document_id) REFERENCES documents (id) ON DELETE CASCADE NOT DEFERRABLE');
        $this->addSql('ALTER TABLE annotations ADD CONSTRAINT FK_48931805F675F31B FOREIGN KEY (author_id) REFERENCES participants (id) ON DELETE CASCADE NOT DEFERRABLE');
        $this->addSql('ALTER TABLE annotations ADD CONSTRAINT FK_489318053CF39FC6 FOREIGN KEY (parent_annotation_id) REFERENCES annotations (id) ON DELETE CASCADE NOT DEFERRABLE');
        $this->addSql('ALTER TABLE annotations ADD CONSTRAINT FK_489318056713A32B FOREIGN KEY (resolved_by_id) REFERENCES participants (id) ON DELETE SET NULL NOT DEFERRABLE');
        $this->addSql('ALTER TABLE decisions ADD CONSTRAINT FK_638DAA17613FECDF FOREIGN KEY (session_id) REFERENCES sessions (id) ON DELETE CASCADE NOT DEFERRABLE');
        $this->addSql('ALTER TABLE decisions ADD CONSTRAINT FK_638DAA172B1068DF FOREIGN KEY (linked_document_id) REFERENCES documents (id) ON DELETE SET NULL NOT DEFERRABLE');
        $this->addSql('ALTER TABLE document_versions ADD CONSTRAINT FK_961DB18BC33F7837 FOREIGN KEY (document_id) REFERENCES documents (id) ON DELETE CASCADE NOT DEFERRABLE');
        $this->addSql('ALTER TABLE document_versions ADD CONSTRAINT FK_961DB18BF675F31B FOREIGN KEY (author_id) REFERENCES participants (id) ON DELETE SET NULL NOT DEFERRABLE');
        $this->addSql('ALTER TABLE documents ADD CONSTRAINT FK_A2B07288613FECDF FOREIGN KEY (session_id) REFERENCES sessions (id) ON DELETE CASCADE NOT DEFERRABLE');
        $this->addSql('ALTER TABLE documents ADD CONSTRAINT FK_A2B07288727ACA70 FOREIGN KEY (parent_id) REFERENCES documents (id) ON DELETE CASCADE NOT DEFERRABLE');
        $this->addSql('ALTER TABLE participants ADD CONSTRAINT FK_71697092613FECDF FOREIGN KEY (session_id) REFERENCES sessions (id) ON DELETE CASCADE NOT DEFERRABLE');
        $this->addSql('ALTER TABLE votes ADD CONSTRAINT FK_518B7ACFBDEE7539 FOREIGN KEY (decision_id) REFERENCES decisions (id) ON DELETE CASCADE NOT DEFERRABLE');
        $this->addSql('ALTER TABLE votes ADD CONSTRAINT FK_518B7ACF9D1C3019 FOREIGN KEY (participant_id) REFERENCES participants (id) ON DELETE CASCADE NOT DEFERRABLE');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE annotations DROP CONSTRAINT FK_48931805C33F7837');
        $this->addSql('ALTER TABLE annotations DROP CONSTRAINT FK_48931805F675F31B');
        $this->addSql('ALTER TABLE annotations DROP CONSTRAINT FK_489318053CF39FC6');
        $this->addSql('ALTER TABLE annotations DROP CONSTRAINT FK_489318056713A32B');
        $this->addSql('ALTER TABLE decisions DROP CONSTRAINT FK_638DAA17613FECDF');
        $this->addSql('ALTER TABLE decisions DROP CONSTRAINT FK_638DAA172B1068DF');
        $this->addSql('ALTER TABLE document_versions DROP CONSTRAINT FK_961DB18BC33F7837');
        $this->addSql('ALTER TABLE document_versions DROP CONSTRAINT FK_961DB18BF675F31B');
        $this->addSql('ALTER TABLE documents DROP CONSTRAINT FK_A2B07288613FECDF');
        $this->addSql('ALTER TABLE documents DROP CONSTRAINT FK_A2B07288727ACA70');
        $this->addSql('ALTER TABLE participants DROP CONSTRAINT FK_71697092613FECDF');
        $this->addSql('ALTER TABLE votes DROP CONSTRAINT FK_518B7ACFBDEE7539');
        $this->addSql('ALTER TABLE votes DROP CONSTRAINT FK_518B7ACF9D1C3019');
        $this->addSql('DROP TABLE annotations');
        $this->addSql('DROP TABLE decisions');
        $this->addSql('DROP TABLE document_versions');
        $this->addSql('DROP TABLE documents');
        $this->addSql('DROP TABLE participants');
        $this->addSql('DROP TABLE sessions');
        $this->addSql('DROP TABLE votes');
    }
}

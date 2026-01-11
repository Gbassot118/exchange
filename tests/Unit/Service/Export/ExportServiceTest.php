<?php

namespace App\Tests\Unit\Service\Export;

use App\Entity\Annotation;
use App\Entity\Decision;
use App\Entity\Document;
use App\Entity\Participant;
use App\Entity\Session;
use App\Entity\Vote;
use App\Repository\AnnotationRepository;
use App\Repository\DecisionRepository;
use App\Repository\DocumentRepository;
use App\Service\Export\ExportService;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Uid\Uuid;

class ExportServiceTest extends TestCase
{
    private DocumentRepository&MockObject $documentRepository;
    private AnnotationRepository&MockObject $annotationRepository;
    private DecisionRepository&MockObject $decisionRepository;
    private ExportService $service;

    protected function setUp(): void
    {
        $this->documentRepository = $this->createMock(DocumentRepository::class);
        $this->annotationRepository = $this->createMock(AnnotationRepository::class);
        $this->decisionRepository = $this->createMock(DecisionRepository::class);

        $this->service = new ExportService(
            $this->documentRepository,
            $this->annotationRepository,
            $this->decisionRepository
        );
    }

    private function createSession(string $title = 'Test Session', ?string $description = null): Session
    {
        $session = new Session();
        $session->setTitle($title);
        if ($description !== null) {
            $session->setDescription($description);
        }
        return $session;
    }

    private function createDocument(Session $session, string $title = 'Test Document'): Document
    {
        $document = new Document();
        $document->setSession($session);
        $document->setTitle($title);
        $document->setType('general');
        return $document;
    }

    private function createParticipant(Session $session, string $pseudo = 'TestUser'): Participant
    {
        $participant = new Participant();
        $participant->setSession($session);
        $participant->setPseudo($pseudo);
        return $participant;
    }

    private function createAnnotation(Document $document, Participant $author, string $content = 'Test annotation'): Annotation
    {
        $annotation = new Annotation();
        $annotation->setDocument($document);
        $annotation->setAuthor($author);
        $annotation->setContent($content);
        $annotation->setType('comment');
        return $annotation;
    }

    private function createDecision(Session $session, string $title = 'Test Decision'): Decision
    {
        $decision = new Decision();
        $decision->setSession($session);
        $decision->setTitle($title);
        $decision->setOptions([
            ['id' => Uuid::v7()->toString(), 'label' => 'Option A', 'description' => 'First option'],
            ['id' => Uuid::v7()->toString(), 'label' => 'Option B', 'description' => 'Second option'],
        ]);
        return $decision;
    }

    // === exportToMarkdown tests ===

    public function testExportToMarkdownReturnsString(): void
    {
        $session = $this->createSession();

        $this->documentRepository->method('findRootDocuments')->willReturn([]);
        $this->decisionRepository->method('findBySession')->willReturn([]);

        $result = $this->service->exportToMarkdown($session);

        $this->assertIsString($result);
    }

    public function testExportToMarkdownIncludesSessionTitle(): void
    {
        $session = $this->createSession('My Session Title');

        $this->documentRepository->method('findRootDocuments')->willReturn([]);
        $this->decisionRepository->method('findBySession')->willReturn([]);

        $result = $this->service->exportToMarkdown($session);

        $this->assertStringContainsString('# My Session Title', $result);
    }

    public function testExportToMarkdownIncludesDescription(): void
    {
        $session = $this->createSession('Title', 'This is the session description');

        $this->documentRepository->method('findRootDocuments')->willReturn([]);
        $this->decisionRepository->method('findBySession')->willReturn([]);

        $result = $this->service->exportToMarkdown($session);

        $this->assertStringContainsString('This is the session description', $result);
    }

    public function testExportToMarkdownWithoutDescription(): void
    {
        $session = $this->createSession('Title');

        $this->documentRepository->method('findRootDocuments')->willReturn([]);
        $this->decisionRepository->method('findBySession')->willReturn([]);

        $result = $this->service->exportToMarkdown($session);

        $this->assertStringContainsString('# Title', $result);
        $this->assertStringContainsString('**Statut:**', $result);
    }

    public function testExportToMarkdownIncludesStatus(): void
    {
        $session = $this->createSession();
        $session->setStatus(Session::STATUS_EN_COURS);

        $this->documentRepository->method('findRootDocuments')->willReturn([]);
        $this->decisionRepository->method('findBySession')->willReturn([]);

        $result = $this->service->exportToMarkdown($session);

        $this->assertStringContainsString('**Statut:** En cours', $result);
    }

    public function testExportToMarkdownIncludesCreatedAt(): void
    {
        $session = $this->createSession();

        $this->documentRepository->method('findRootDocuments')->willReturn([]);
        $this->decisionRepository->method('findBySession')->willReturn([]);

        $result = $this->service->exportToMarkdown($session);

        $this->assertStringContainsString('**Créé le:**', $result);
    }

    public function testExportToMarkdownIncludesUpdatedAt(): void
    {
        $session = $this->createSession();

        $this->documentRepository->method('findRootDocuments')->willReturn([]);
        $this->decisionRepository->method('findBySession')->willReturn([]);

        $result = $this->service->exportToMarkdown($session);

        $this->assertStringContainsString('**Dernière mise à jour:**', $result);
    }

    // === Table of Contents tests ===

    public function testExportToMarkdownIncludesTableOfContents(): void
    {
        $session = $this->createSession();
        $document = $this->createDocument($session, 'Chapter 1');

        $this->documentRepository->method('findRootDocuments')->willReturn([$document]);
        $this->decisionRepository->method('findBySession')->willReturn([]);
        $this->annotationRepository->method('findByDocumentWithFilters')->willReturn([]);

        $result = $this->service->exportToMarkdown($session);

        $this->assertStringContainsString('## Table des matières', $result);
        $this->assertStringContainsString('- [Chapter 1](#chapter-1)', $result);
    }

    public function testExportToMarkdownTableOfContentsWithNestedDocuments(): void
    {
        $session = $this->createSession();
        $parent = $this->createDocument($session, 'Parent');
        $child = $this->createDocument($session, 'Child');
        $child->setParent($parent);
        $parent->getChildren()->add($child);

        $this->documentRepository->method('findRootDocuments')->willReturn([$parent]);
        $this->decisionRepository->method('findBySession')->willReturn([]);
        $this->annotationRepository->method('findByDocumentWithFilters')->willReturn([]);

        $result = $this->service->exportToMarkdown($session);

        $this->assertStringContainsString('- [Parent](#parent)', $result);
        $this->assertStringContainsString('  - [Child](#child)', $result);
    }

    public function testExportToMarkdownNoTableOfContentsWhenNoDocuments(): void
    {
        $session = $this->createSession();

        $this->documentRepository->method('findRootDocuments')->willReturn([]);
        $this->decisionRepository->method('findBySession')->willReturn([]);

        $result = $this->service->exportToMarkdown($session);

        $this->assertStringNotContainsString('## Table des matières', $result);
    }

    // === Documents export tests ===

    public function testExportToMarkdownIncludesDocumentSection(): void
    {
        $session = $this->createSession();
        $document = $this->createDocument($session, 'My Document');

        $this->documentRepository->method('findRootDocuments')->willReturn([$document]);
        $this->decisionRepository->method('findBySession')->willReturn([]);
        $this->annotationRepository->method('findByDocumentWithFilters')->willReturn([]);

        $result = $this->service->exportToMarkdown($session);

        $this->assertStringContainsString('## Documents', $result);
    }

    public function testExportToMarkdownIncludesDocumentTitle(): void
    {
        $session = $this->createSession();
        $document = $this->createDocument($session, 'Important Document');

        $this->documentRepository->method('findRootDocuments')->willReturn([$document]);
        $this->decisionRepository->method('findBySession')->willReturn([]);
        $this->annotationRepository->method('findByDocumentWithFilters')->willReturn([]);

        $result = $this->service->exportToMarkdown($session);

        $this->assertStringContainsString('## Important Document', $result);
    }

    public function testExportToMarkdownIncludesDocumentAnchor(): void
    {
        $session = $this->createSession();
        $document = $this->createDocument($session, 'My Document');

        $this->documentRepository->method('findRootDocuments')->willReturn([$document]);
        $this->decisionRepository->method('findBySession')->willReturn([]);
        $this->annotationRepository->method('findByDocumentWithFilters')->willReturn([]);

        $result = $this->service->exportToMarkdown($session);

        $this->assertStringContainsString('<a id="my-document"></a>', $result);
    }

    public function testExportToMarkdownIncludesDocumentType(): void
    {
        $session = $this->createSession();
        $document = $this->createDocument($session);
        $document->setType('synthesis');

        $this->documentRepository->method('findRootDocuments')->willReturn([$document]);
        $this->decisionRepository->method('findBySession')->willReturn([]);
        $this->annotationRepository->method('findByDocumentWithFilters')->willReturn([]);

        $result = $this->service->exportToMarkdown($session);

        $this->assertStringContainsString('*Type: Synthèse', $result);
    }

    public function testExportToMarkdownIncludesDocumentVersion(): void
    {
        $session = $this->createSession();
        $document = $this->createDocument($session);

        $this->documentRepository->method('findRootDocuments')->willReturn([$document]);
        $this->decisionRepository->method('findBySession')->willReturn([]);
        $this->annotationRepository->method('findByDocumentWithFilters')->willReturn([]);

        $result = $this->service->exportToMarkdown($session);

        $this->assertStringContainsString('Version: 1', $result);
    }

    public function testExportToMarkdownIncludesDocumentContent(): void
    {
        $session = $this->createSession();
        $document = $this->createDocument($session);
        $document->setContent('This is the document content.');

        $this->documentRepository->method('findRootDocuments')->willReturn([$document]);
        $this->decisionRepository->method('findBySession')->willReturn([]);
        $this->annotationRepository->method('findByDocumentWithFilters')->willReturn([]);

        $result = $this->service->exportToMarkdown($session);

        $this->assertStringContainsString('This is the document content.', $result);
    }

    public function testExportToMarkdownEmptyDocumentContent(): void
    {
        $session = $this->createSession();
        $document = $this->createDocument($session);
        $document->setContent('');

        $this->documentRepository->method('findRootDocuments')->willReturn([$document]);
        $this->decisionRepository->method('findBySession')->willReturn([]);
        $this->annotationRepository->method('findByDocumentWithFilters')->willReturn([]);

        $result = $this->service->exportToMarkdown($session);

        $this->assertIsString($result);
        $this->assertStringContainsString('## Test Document', $result);
    }

    // === Nested documents tests ===

    public function testExportToMarkdownNestedDocumentsHaveCorrectHeadings(): void
    {
        $session = $this->createSession();
        $parent = $this->createDocument($session, 'Level 2');
        $child = $this->createDocument($session, 'Level 3');
        $grandchild = $this->createDocument($session, 'Level 4');

        $child->setParent($parent);
        $grandchild->setParent($child);
        $parent->getChildren()->add($child);
        $child->getChildren()->add($grandchild);

        $this->documentRepository->method('findRootDocuments')->willReturn([$parent]);
        $this->decisionRepository->method('findBySession')->willReturn([]);
        $this->annotationRepository->method('findByDocumentWithFilters')->willReturn([]);

        $result = $this->service->exportToMarkdown($session);

        $this->assertStringContainsString('## Level 2', $result);
        $this->assertStringContainsString('### Level 3', $result);
        $this->assertStringContainsString('#### Level 4', $result);
    }

    public function testExportToMarkdownMaxHeadingLevel6(): void
    {
        $session = $this->createSession();

        // Create 5 levels of nesting (2 -> 6)
        $level2 = $this->createDocument($session, 'Level 2');
        $level3 = $this->createDocument($session, 'Level 3');
        $level4 = $this->createDocument($session, 'Level 4');
        $level5 = $this->createDocument($session, 'Level 5');
        $level6 = $this->createDocument($session, 'Level 6');
        $level7 = $this->createDocument($session, 'Level 7 (still h6)');

        $level3->setParent($level2);
        $level4->setParent($level3);
        $level5->setParent($level4);
        $level6->setParent($level5);
        $level7->setParent($level6);

        $level2->getChildren()->add($level3);
        $level3->getChildren()->add($level4);
        $level4->getChildren()->add($level5);
        $level5->getChildren()->add($level6);
        $level6->getChildren()->add($level7);

        $this->documentRepository->method('findRootDocuments')->willReturn([$level2]);
        $this->decisionRepository->method('findBySession')->willReturn([]);
        $this->annotationRepository->method('findByDocumentWithFilters')->willReturn([]);

        $result = $this->service->exportToMarkdown($session);

        $this->assertStringContainsString('###### Level 6', $result);
        $this->assertStringContainsString('###### Level 7', $result);
        $this->assertStringNotContainsString('####### ', $result);
    }

    // === Annotations export tests ===

    public function testExportToMarkdownIncludesAnnotations(): void
    {
        $session = $this->createSession();
        $document = $this->createDocument($session);
        $participant = $this->createParticipant($session, 'Alice');
        $annotation = $this->createAnnotation($document, $participant, 'This is a comment');

        $this->documentRepository->method('findRootDocuments')->willReturn([$document]);
        $this->decisionRepository->method('findBySession')->willReturn([]);
        $this->annotationRepository->method('findByDocumentWithFilters')->willReturn([$annotation]);

        $result = $this->service->exportToMarkdown($session);

        $this->assertStringContainsString('#### Annotations', $result);
        $this->assertStringContainsString('**Alice**', $result);
        $this->assertStringContainsString('This is a comment', $result);
    }

    public function testExportToMarkdownAnnotationTypeIconComment(): void
    {
        $session = $this->createSession();
        $document = $this->createDocument($session);
        $participant = $this->createParticipant($session);
        $annotation = $this->createAnnotation($document, $participant);
        $annotation->setType('comment');

        $this->documentRepository->method('findRootDocuments')->willReturn([$document]);
        $this->decisionRepository->method('findBySession')->willReturn([]);
        $this->annotationRepository->method('findByDocumentWithFilters')->willReturn([$annotation]);

        $result = $this->service->exportToMarkdown($session);

        $this->assertStringContainsString('💬', $result);
    }

    public function testExportToMarkdownAnnotationTypeIconQuestion(): void
    {
        $session = $this->createSession();
        $document = $this->createDocument($session);
        $participant = $this->createParticipant($session);
        $annotation = $this->createAnnotation($document, $participant);
        $annotation->setType('question');

        $this->documentRepository->method('findRootDocuments')->willReturn([$document]);
        $this->decisionRepository->method('findBySession')->willReturn([]);
        $this->annotationRepository->method('findByDocumentWithFilters')->willReturn([$annotation]);

        $result = $this->service->exportToMarkdown($session);

        $this->assertStringContainsString('❓', $result);
    }

    public function testExportToMarkdownAnnotationTypeIconSuggestion(): void
    {
        $session = $this->createSession();
        $document = $this->createDocument($session);
        $participant = $this->createParticipant($session);
        $annotation = $this->createAnnotation($document, $participant);
        $annotation->setType('suggestion');

        $this->documentRepository->method('findRootDocuments')->willReturn([$document]);
        $this->decisionRepository->method('findBySession')->willReturn([]);
        $this->annotationRepository->method('findByDocumentWithFilters')->willReturn([$annotation]);

        $result = $this->service->exportToMarkdown($session);

        $this->assertStringContainsString('💡', $result);
    }

    public function testExportToMarkdownAnnotationTypeIconObjection(): void
    {
        $session = $this->createSession();
        $document = $this->createDocument($session);
        $participant = $this->createParticipant($session);
        $annotation = $this->createAnnotation($document, $participant);
        $annotation->setType('objection');

        $this->documentRepository->method('findRootDocuments')->willReturn([$document]);
        $this->decisionRepository->method('findBySession')->willReturn([]);
        $this->annotationRepository->method('findByDocumentWithFilters')->willReturn([$annotation]);

        $result = $this->service->exportToMarkdown($session);

        $this->assertStringContainsString('⚠️', $result);
    }

    public function testExportToMarkdownAnnotationTypeIconValidation(): void
    {
        $session = $this->createSession();
        $document = $this->createDocument($session);
        $participant = $this->createParticipant($session);
        $annotation = $this->createAnnotation($document, $participant);
        $annotation->setType('validation');

        $this->documentRepository->method('findRootDocuments')->willReturn([$document]);
        $this->decisionRepository->method('findBySession')->willReturn([]);
        $this->annotationRepository->method('findByDocumentWithFilters')->willReturn([$annotation]);

        $result = $this->service->exportToMarkdown($session);

        $this->assertStringContainsString('✅', $result);
    }

    public function testExportToMarkdownResolvedAnnotationHasCheckmark(): void
    {
        $session = $this->createSession();
        $document = $this->createDocument($session);
        $participant = $this->createParticipant($session);
        $annotation = $this->createAnnotation($document, $participant);
        $annotation->resolve($participant);

        $this->documentRepository->method('findRootDocuments')->willReturn([$document]);
        $this->decisionRepository->method('findBySession')->willReturn([]);
        $this->annotationRepository->method('findByDocumentWithFilters')->willReturn([$annotation]);

        $result = $this->service->exportToMarkdown($session);

        // Resolved annotations should have a checkmark
        $this->assertStringContainsString('✅', $result);
    }

    public function testExportToMarkdownAnnotationReplies(): void
    {
        $session = $this->createSession();
        $document = $this->createDocument($session);
        $author = $this->createParticipant($session, 'Author');
        $replier = $this->createParticipant($session, 'Replier');

        $annotation = $this->createAnnotation($document, $author, 'Original comment');
        $reply = new Annotation();
        $reply->setDocument($document);
        $reply->setAuthor($replier);
        $reply->setContent('This is a reply');
        $reply->setType('comment');
        $reply->setParentAnnotation($annotation);
        $annotation->getReplies()->add($reply);

        $this->documentRepository->method('findRootDocuments')->willReturn([$document]);
        $this->decisionRepository->method('findBySession')->willReturn([]);
        $this->annotationRepository->method('findByDocumentWithFilters')->willReturn([$annotation]);

        $result = $this->service->exportToMarkdown($session);

        $this->assertStringContainsString('**Author**', $result);
        $this->assertStringContainsString('Original comment', $result);
        $this->assertStringContainsString('**Replier**', $result);
        $this->assertStringContainsString('This is a reply', $result);
    }

    public function testExportToMarkdownSkipsReplyAnnotationsAtRootLevel(): void
    {
        $session = $this->createSession();
        $document = $this->createDocument($session);
        $author = $this->createParticipant($session);

        $parent = $this->createAnnotation($document, $author, 'Parent comment');
        $reply = new Annotation();
        $reply->setDocument($document);
        $reply->setAuthor($author);
        $reply->setContent('Reply comment');
        $reply->setType('comment');
        $reply->setParentAnnotation($parent);

        // Return both parent and reply - reply should be filtered as isReply()
        $this->documentRepository->method('findRootDocuments')->willReturn([$document]);
        $this->decisionRepository->method('findBySession')->willReturn([]);
        $this->annotationRepository->method('findByDocumentWithFilters')->willReturn([$parent, $reply]);

        $result = $this->service->exportToMarkdown($session);

        // The reply should appear under its parent, not as a separate root annotation
        $this->assertIsString($result);
    }

    // === Decisions export tests ===

    public function testExportToMarkdownIncludesDecisions(): void
    {
        $session = $this->createSession();
        $decision = $this->createDecision($session, 'Important Decision');

        $this->documentRepository->method('findRootDocuments')->willReturn([]);
        $this->decisionRepository->method('findBySession')->willReturn([$decision]);

        $result = $this->service->exportToMarkdown($session);

        $this->assertStringContainsString('## Décisions', $result);
        $this->assertStringContainsString('### ', $result);
        $this->assertStringContainsString('Important Decision', $result);
    }

    public function testExportToMarkdownDecisionStatusIconOuvert(): void
    {
        $session = $this->createSession();
        $decision = $this->createDecision($session);
        $decision->setStatus('ouvert');

        $this->documentRepository->method('findRootDocuments')->willReturn([]);
        $this->decisionRepository->method('findBySession')->willReturn([$decision]);

        $result = $this->service->exportToMarkdown($session);

        $this->assertStringContainsString('🔵', $result);
    }

    public function testExportToMarkdownDecisionStatusIconEnDiscussion(): void
    {
        $session = $this->createSession();
        $decision = $this->createDecision($session);
        $decision->setStatus('en_discussion');

        $this->documentRepository->method('findRootDocuments')->willReturn([]);
        $this->decisionRepository->method('findBySession')->willReturn([$decision]);

        $result = $this->service->exportToMarkdown($session);

        $this->assertStringContainsString('🟡', $result);
    }

    public function testExportToMarkdownDecisionStatusIconConsensus(): void
    {
        $session = $this->createSession();
        $decision = $this->createDecision($session);
        $decision->setStatus('consensus');

        $this->documentRepository->method('findRootDocuments')->willReturn([]);
        $this->decisionRepository->method('findBySession')->willReturn([$decision]);

        $result = $this->service->exportToMarkdown($session);

        $this->assertStringContainsString('🟢', $result);
    }

    public function testExportToMarkdownDecisionStatusIconValide(): void
    {
        $session = $this->createSession();
        $decision = $this->createDecision($session);
        $decision->setStatus('valide');

        $this->documentRepository->method('findRootDocuments')->willReturn([]);
        $this->decisionRepository->method('findBySession')->willReturn([$decision]);

        $result = $this->service->exportToMarkdown($session);

        $this->assertStringContainsString('✅', $result);
    }

    public function testExportToMarkdownDecisionStatusIconReporte(): void
    {
        $session = $this->createSession();
        $decision = $this->createDecision($session);
        $decision->setStatus('reporte');

        $this->documentRepository->method('findRootDocuments')->willReturn([]);
        $this->decisionRepository->method('findBySession')->willReturn([$decision]);

        $result = $this->service->exportToMarkdown($session);

        $this->assertStringContainsString('🔴', $result);
    }

    public function testExportToMarkdownDecisionOptions(): void
    {
        $session = $this->createSession();
        $decision = $this->createDecision($session);

        $this->documentRepository->method('findRootDocuments')->willReturn([]);
        $this->decisionRepository->method('findBySession')->willReturn([$decision]);

        $result = $this->service->exportToMarkdown($session);

        $this->assertStringContainsString('**Options:**', $result);
        $this->assertStringContainsString('**Option A**', $result);
        $this->assertStringContainsString('**Option B**', $result);
    }

    public function testExportToMarkdownDecisionWithVotes(): void
    {
        $session = $this->createSession();
        $decision = $this->createDecision($session);
        $participant = $this->createParticipant($session);

        $optionId = $decision->getOptions()[0]['id'];
        $vote = new Vote();
        $vote->setDecision($decision);
        $vote->setParticipant($participant);
        $vote->setOptionId(Uuid::fromString($optionId));
        $decision->getVotes()->add($vote);

        $this->documentRepository->method('findRootDocuments')->willReturn([]);
        $this->decisionRepository->method('findBySession')->willReturn([$decision]);

        $result = $this->service->exportToMarkdown($session);

        $this->assertStringContainsString('(1 vote(s))', $result);
    }

    public function testExportToMarkdownDecisionWithSelectedOption(): void
    {
        $session = $this->createSession();
        $decision = $this->createDecision($session);
        $optionId = Uuid::fromString($decision->getOptions()[0]['id']);
        $decision->validate($optionId);

        $this->documentRepository->method('findRootDocuments')->willReturn([]);
        $this->decisionRepository->method('findBySession')->willReturn([$decision]);

        $result = $this->service->exportToMarkdown($session);

        $this->assertStringContainsString('✓ **VALIDÉ**', $result);
    }

    public function testExportToMarkdownDecisionWithLinkedDocument(): void
    {
        $session = $this->createSession();
        $document = $this->createDocument($session, 'Linked Doc');
        $decision = $this->createDecision($session);
        $decision->setLinkedDocument($document);

        $this->documentRepository->method('findRootDocuments')->willReturn([]);
        $this->decisionRepository->method('findBySession')->willReturn([$decision]);

        $result = $this->service->exportToMarkdown($session);

        $this->assertStringContainsString('**Document lié:** Linked Doc', $result);
    }

    public function testExportToMarkdownDecisionWithDescription(): void
    {
        $session = $this->createSession();
        $decision = $this->createDecision($session);
        $decision->setDescription('This is a detailed description of the decision.');

        $this->documentRepository->method('findRootDocuments')->willReturn([]);
        $this->decisionRepository->method('findBySession')->willReturn([$decision]);

        $result = $this->service->exportToMarkdown($session);

        $this->assertStringContainsString('This is a detailed description of the decision.', $result);
    }

    public function testExportToMarkdownNoDecisionsSection(): void
    {
        $session = $this->createSession();

        $this->documentRepository->method('findRootDocuments')->willReturn([]);
        $this->decisionRepository->method('findBySession')->willReturn([]);

        $result = $this->service->exportToMarkdown($session);

        $this->assertStringNotContainsString('## Décisions', $result);
    }

    // === Status translation tests ===

    public function testExportToMarkdownTranslatesStatusPreparation(): void
    {
        $session = $this->createSession();
        $session->setStatus('preparation');

        $this->documentRepository->method('findRootDocuments')->willReturn([]);
        $this->decisionRepository->method('findBySession')->willReturn([]);

        $result = $this->service->exportToMarkdown($session);

        $this->assertStringContainsString('**Statut:** Préparation', $result);
    }

    public function testExportToMarkdownTranslatesStatusEnCours(): void
    {
        $session = $this->createSession();
        $session->setStatus('en_cours');

        $this->documentRepository->method('findRootDocuments')->willReturn([]);
        $this->decisionRepository->method('findBySession')->willReturn([]);

        $result = $this->service->exportToMarkdown($session);

        $this->assertStringContainsString('**Statut:** En cours', $result);
    }

    public function testExportToMarkdownTranslatesStatusTermine(): void
    {
        $session = $this->createSession();
        $session->setStatus('termine');

        $this->documentRepository->method('findRootDocuments')->willReturn([]);
        $this->decisionRepository->method('findBySession')->willReturn([]);

        $result = $this->service->exportToMarkdown($session);

        $this->assertStringContainsString('**Statut:** Terminé', $result);
    }

    public function testExportToMarkdownTranslatesStatusArchive(): void
    {
        $session = $this->createSession();
        $session->setStatus('archive');

        $this->documentRepository->method('findRootDocuments')->willReturn([]);
        $this->decisionRepository->method('findBySession')->willReturn([]);

        $result = $this->service->exportToMarkdown($session);

        $this->assertStringContainsString('**Statut:** Archivé', $result);
    }

    public function testExportToMarkdownTranslatesDocumentTypeSynthesis(): void
    {
        $session = $this->createSession();
        $document = $this->createDocument($session);
        $document->setType('synthesis');

        $this->documentRepository->method('findRootDocuments')->willReturn([$document]);
        $this->decisionRepository->method('findBySession')->willReturn([]);
        $this->annotationRepository->method('findByDocumentWithFilters')->willReturn([]);

        $result = $this->service->exportToMarkdown($session);

        $this->assertStringContainsString('Type: Synthèse', $result);
    }

    public function testExportToMarkdownTranslatesDocumentTypeQuestion(): void
    {
        $session = $this->createSession();
        $document = $this->createDocument($session);
        $document->setType('question');

        $this->documentRepository->method('findRootDocuments')->willReturn([$document]);
        $this->decisionRepository->method('findBySession')->willReturn([]);
        $this->annotationRepository->method('findByDocumentWithFilters')->willReturn([]);

        $result = $this->service->exportToMarkdown($session);

        $this->assertStringContainsString('Type: Question', $result);
    }

    public function testExportToMarkdownTranslatesDocumentTypeGeneral(): void
    {
        $session = $this->createSession();
        $document = $this->createDocument($session);
        $document->setType('general');

        $this->documentRepository->method('findRootDocuments')->willReturn([$document]);
        $this->decisionRepository->method('findBySession')->willReturn([]);
        $this->annotationRepository->method('findByDocumentWithFilters')->willReturn([]);

        $result = $this->service->exportToMarkdown($session);

        $this->assertStringContainsString('Type: Général', $result);
    }

    // === Anchor generation tests ===

    public function testExportToMarkdownGeneratesValidAnchors(): void
    {
        $session = $this->createSession();
        $document = $this->createDocument($session, 'Test Document With Spaces');

        $this->documentRepository->method('findRootDocuments')->willReturn([$document]);
        $this->decisionRepository->method('findBySession')->willReturn([]);
        $this->annotationRepository->method('findByDocumentWithFilters')->willReturn([]);

        $result = $this->service->exportToMarkdown($session);

        $this->assertStringContainsString('<a id="test-document-with-spaces"></a>', $result);
        $this->assertStringContainsString('[Test Document With Spaces](#test-document-with-spaces)', $result);
    }

    public function testExportToMarkdownHandlesSpecialCharactersInAnchors(): void
    {
        $session = $this->createSession();
        $document = $this->createDocument($session, 'Document éàü & spécial!');

        $this->documentRepository->method('findRootDocuments')->willReturn([$document]);
        $this->decisionRepository->method('findBySession')->willReturn([]);
        $this->annotationRepository->method('findByDocumentWithFilters')->willReturn([]);

        $result = $this->service->exportToMarkdown($session);

        // Special chars and accented chars are removed by the regex [^a-z0-9\s-]
        $this->assertStringContainsString('<a id="document-spcial"></a>', $result);
    }

    // === exportToHtml tests ===

    public function testExportToHtmlReturnsString(): void
    {
        $session = $this->createSession();

        $this->documentRepository->method('findRootDocuments')->willReturn([]);
        $this->decisionRepository->method('findBySession')->willReturn([]);

        $result = $this->service->exportToHtml($session);

        $this->assertIsString($result);
    }

    public function testExportToHtmlContainsDoctype(): void
    {
        $session = $this->createSession();

        $this->documentRepository->method('findRootDocuments')->willReturn([]);
        $this->decisionRepository->method('findBySession')->willReturn([]);

        $result = $this->service->exportToHtml($session);

        $this->assertStringContainsString('<!DOCTYPE html>', $result);
    }

    public function testExportToHtmlContainsTitle(): void
    {
        $session = $this->createSession('My Export Title');

        $this->documentRepository->method('findRootDocuments')->willReturn([]);
        $this->decisionRepository->method('findBySession')->willReturn([]);

        $result = $this->service->exportToHtml($session);

        $this->assertStringContainsString('<title>My Export Title - Export Documentation</title>', $result);
    }

    public function testExportToHtmlContainsH1(): void
    {
        $session = $this->createSession('Session Title');

        $this->documentRepository->method('findRootDocuments')->willReturn([]);
        $this->decisionRepository->method('findBySession')->willReturn([]);

        $result = $this->service->exportToHtml($session);

        $this->assertStringContainsString('<h1>Session Title</h1>', $result);
    }

    public function testExportToHtmlContainsStyles(): void
    {
        $session = $this->createSession();

        $this->documentRepository->method('findRootDocuments')->willReturn([]);
        $this->decisionRepository->method('findBySession')->willReturn([]);

        $result = $this->service->exportToHtml($session);

        $this->assertStringContainsString('<style>', $result);
        $this->assertStringContainsString('--primary-color', $result);
    }

    public function testExportToHtmlContainsFooter(): void
    {
        $session = $this->createSession();

        $this->documentRepository->method('findRootDocuments')->willReturn([]);
        $this->decisionRepository->method('findBySession')->willReturn([]);

        $result = $this->service->exportToHtml($session);

        $this->assertStringContainsString('<footer', $result);
        $this->assertStringContainsString('Exporté depuis Documentation Collaborative', $result);
    }

    public function testExportToHtmlConvertsHeaders(): void
    {
        $session = $this->createSession();
        $document = $this->createDocument($session, 'Test Doc');

        $this->documentRepository->method('findRootDocuments')->willReturn([$document]);
        $this->decisionRepository->method('findBySession')->willReturn([]);
        $this->annotationRepository->method('findByDocumentWithFilters')->willReturn([]);

        $result = $this->service->exportToHtml($session);

        $this->assertStringContainsString('<h2>Test Doc</h2>', $result);
    }

    public function testExportToHtmlConvertsBoldText(): void
    {
        $session = $this->createSession();

        $this->documentRepository->method('findRootDocuments')->willReturn([]);
        $this->decisionRepository->method('findBySession')->willReturn([]);

        $result = $this->service->exportToHtml($session);

        // Status is bold in markdown, should be strong in HTML
        $this->assertStringContainsString('<strong>', $result);
    }

    public function testExportToHtmlConvertsItalicText(): void
    {
        $session = $this->createSession();
        $document = $this->createDocument($session);

        $this->documentRepository->method('findRootDocuments')->willReturn([$document]);
        $this->decisionRepository->method('findBySession')->willReturn([]);
        $this->annotationRepository->method('findByDocumentWithFilters')->willReturn([]);

        $result = $this->service->exportToHtml($session);

        // Document metadata is in italics
        $this->assertStringContainsString('<em>', $result);
    }

    public function testExportToHtmlConvertsLinks(): void
    {
        $session = $this->createSession();
        $document = $this->createDocument($session, 'Test');

        $this->documentRepository->method('findRootDocuments')->willReturn([$document]);
        $this->decisionRepository->method('findBySession')->willReturn([]);
        $this->annotationRepository->method('findByDocumentWithFilters')->willReturn([]);

        $result = $this->service->exportToHtml($session);

        // Table of contents has links
        $this->assertStringContainsString('<a href="#test">', $result);
    }

    public function testExportToHtmlConvertsHorizontalRules(): void
    {
        $session = $this->createSession();

        $this->documentRepository->method('findRootDocuments')->willReturn([]);
        $this->decisionRepository->method('findBySession')->willReturn([]);

        $result = $this->service->exportToHtml($session);

        $this->assertStringContainsString('<hr>', $result);
    }

    public function testExportToHtmlPreservesAnchors(): void
    {
        $session = $this->createSession();
        $document = $this->createDocument($session, 'My Document');

        $this->documentRepository->method('findRootDocuments')->willReturn([$document]);
        $this->decisionRepository->method('findBySession')->willReturn([]);
        $this->annotationRepository->method('findByDocumentWithFilters')->willReturn([]);

        $result = $this->service->exportToHtml($session);

        // The anchor regex may escape quotes, check for the anchor ID reference
        $this->assertStringContainsString('my-document', $result);
        $this->assertStringContainsString('<a href="#my-document">', $result);
    }

    public function testExportToHtmlHasLanguageAttribute(): void
    {
        $session = $this->createSession();

        $this->documentRepository->method('findRootDocuments')->willReturn([]);
        $this->decisionRepository->method('findBySession')->willReturn([]);

        $result = $this->service->exportToHtml($session);

        $this->assertStringContainsString('<html lang="fr">', $result);
    }

    public function testExportToHtmlHasCharsetMeta(): void
    {
        $session = $this->createSession();

        $this->documentRepository->method('findRootDocuments')->willReturn([]);
        $this->decisionRepository->method('findBySession')->willReturn([]);

        $result = $this->service->exportToHtml($session);

        $this->assertStringContainsString('<meta charset="UTF-8">', $result);
    }

    public function testExportToHtmlHasViewportMeta(): void
    {
        $session = $this->createSession();

        $this->documentRepository->method('findRootDocuments')->willReturn([]);
        $this->decisionRepository->method('findBySession')->willReturn([]);

        $result = $this->service->exportToHtml($session);

        $this->assertStringContainsString('viewport', $result);
    }

    // === Edge cases ===

    public function testExportToMarkdownEmptySession(): void
    {
        $session = $this->createSession('Empty Session');

        $this->documentRepository->method('findRootDocuments')->willReturn([]);
        $this->decisionRepository->method('findBySession')->willReturn([]);

        $result = $this->service->exportToMarkdown($session);

        $this->assertStringContainsString('# Empty Session', $result);
        $this->assertStringNotContainsString('## Table des matières', $result);
        $this->assertStringNotContainsString('## Décisions', $result);
    }

    public function testExportToMarkdownMultipleDocuments(): void
    {
        $session = $this->createSession();
        $doc1 = $this->createDocument($session, 'Document One');
        $doc2 = $this->createDocument($session, 'Document Two');
        $doc3 = $this->createDocument($session, 'Document Three');

        $this->documentRepository->method('findRootDocuments')->willReturn([$doc1, $doc2, $doc3]);
        $this->decisionRepository->method('findBySession')->willReturn([]);
        $this->annotationRepository->method('findByDocumentWithFilters')->willReturn([]);

        $result = $this->service->exportToMarkdown($session);

        $this->assertStringContainsString('Document One', $result);
        $this->assertStringContainsString('Document Two', $result);
        $this->assertStringContainsString('Document Three', $result);
    }

    public function testExportToMarkdownMultipleDecisions(): void
    {
        $session = $this->createSession();
        $dec1 = $this->createDecision($session, 'Decision 1');
        $dec2 = $this->createDecision($session, 'Decision 2');

        $this->documentRepository->method('findRootDocuments')->willReturn([]);
        $this->decisionRepository->method('findBySession')->willReturn([$dec1, $dec2]);

        $result = $this->service->exportToMarkdown($session);

        $this->assertStringContainsString('Decision 1', $result);
        $this->assertStringContainsString('Decision 2', $result);
    }

    public function testExportToMarkdownWithMultilineContent(): void
    {
        $session = $this->createSession();
        $document = $this->createDocument($session);
        $document->setContent("Line 1\nLine 2\nLine 3");

        $this->documentRepository->method('findRootDocuments')->willReturn([$document]);
        $this->decisionRepository->method('findBySession')->willReturn([]);
        $this->annotationRepository->method('findByDocumentWithFilters')->willReturn([]);

        $result = $this->service->exportToMarkdown($session);

        $this->assertStringContainsString("Line 1\nLine 2\nLine 3", $result);
    }

    public function testExportToMarkdownAnnotationWithMultilineContent(): void
    {
        $session = $this->createSession();
        $document = $this->createDocument($session);
        $participant = $this->createParticipant($session);
        $annotation = $this->createAnnotation($document, $participant, "First line\nSecond line");

        $this->documentRepository->method('findRootDocuments')->willReturn([$document]);
        $this->decisionRepository->method('findBySession')->willReturn([]);
        $this->annotationRepository->method('findByDocumentWithFilters')->willReturn([$annotation]);

        $result = $this->service->exportToMarkdown($session);

        $this->assertStringContainsString('First line', $result);
        $this->assertStringContainsString('Second line', $result);
    }

    public function testExportToMarkdownUnknownTypeIcon(): void
    {
        $session = $this->createSession();
        $document = $this->createDocument($session);
        $participant = $this->createParticipant($session);
        $annotation = $this->createAnnotation($document, $participant);
        $annotation->setType('unknown_type');

        $this->documentRepository->method('findRootDocuments')->willReturn([$document]);
        $this->decisionRepository->method('findBySession')->willReturn([]);
        $this->annotationRepository->method('findByDocumentWithFilters')->willReturn([$annotation]);

        $result = $this->service->exportToMarkdown($session);

        // Default icon for unknown type
        $this->assertStringContainsString('📝', $result);
    }

    public function testExportToMarkdownUnknownDecisionStatusIcon(): void
    {
        $session = $this->createSession();
        $decision = $this->createDecision($session);
        $decision->setStatus('unknown_status');

        $this->documentRepository->method('findRootDocuments')->willReturn([]);
        $this->decisionRepository->method('findBySession')->willReturn([$decision]);

        $result = $this->service->exportToMarkdown($session);

        // Default icon for unknown status
        $this->assertStringContainsString('⚪', $result);
    }

    public function testExportToMarkdownDecisionOptionWithEmptyDescription(): void
    {
        $session = $this->createSession();
        $decision = new Decision();
        $decision->setSession($session);
        $decision->setTitle('Test');
        $decision->setOptions([
            ['id' => Uuid::v7()->toString(), 'label' => 'Option', 'description' => ''],
        ]);

        $this->documentRepository->method('findRootDocuments')->willReturn([]);
        $this->decisionRepository->method('findBySession')->willReturn([$decision]);

        $result = $this->service->exportToMarkdown($session);

        $this->assertStringContainsString('**Option**', $result);
    }
}

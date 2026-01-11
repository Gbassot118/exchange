<?php

namespace App\Tests\Unit\Service\Mcp;

use App\Entity\Annotation;
use App\Entity\Decision;
use App\Entity\Document;
use App\Entity\Participant;
use App\Entity\Session;
use App\Repository\AnnotationRepository;
use App\Repository\DecisionRepository;
use App\Repository\DocumentRepository;
use App\Repository\ParticipantRepository;
use App\Repository\SessionRepository;
use App\Service\Annotation\AnnotationService;
use App\Service\Decision\DecisionService;
use App\Service\Document\DocumentService;
use App\Service\Mcp\McpService;
use App\Service\Session\SessionService;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Uid\Uuid;

#[AllowMockObjectsWithoutExpectations]
class McpServiceTest extends TestCase
{
    private SessionRepository&MockObject $sessionRepository;
    private DocumentRepository&MockObject $documentRepository;
    private AnnotationRepository&MockObject $annotationRepository;
    private DecisionRepository&MockObject $decisionRepository;
    private ParticipantRepository&MockObject $participantRepository;
    private DocumentService&MockObject $documentService;
    private AnnotationService&MockObject $annotationService;
    private DecisionService&MockObject $decisionService;
    private SessionService&MockObject $sessionService;
    private McpService $service;

    protected function setUp(): void
    {
        $this->sessionRepository = $this->createMock(SessionRepository::class);
        $this->documentRepository = $this->createMock(DocumentRepository::class);
        $this->annotationRepository = $this->createMock(AnnotationRepository::class);
        $this->decisionRepository = $this->createMock(DecisionRepository::class);
        $this->participantRepository = $this->createMock(ParticipantRepository::class);
        $this->documentService = $this->createMock(DocumentService::class);
        $this->annotationService = $this->createMock(AnnotationService::class);
        $this->decisionService = $this->createMock(DecisionService::class);
        $this->sessionService = $this->createMock(SessionService::class);

        $this->service = new McpService(
            $this->sessionRepository,
            $this->documentRepository,
            $this->annotationRepository,
            $this->decisionRepository,
            $this->participantRepository,
            $this->documentService,
            $this->annotationService,
            $this->decisionService,
            $this->sessionService
        );
    }

    private function createSession(): Session
    {
        $session = new Session();
        $session->setTitle('Test Session');
        return $session;
    }

    private function createDocument(Session $session): Document
    {
        $document = new Document();
        $document->setSession($session);
        $document->setTitle('Test Document');
        return $document;
    }

    private function createParticipant(Session $session): Participant
    {
        $participant = new Participant();
        $participant->setSession($session);
        $participant->setPseudo('TestUser');
        return $participant;
    }

    private function createAnnotation(Document $document, Participant $author): Annotation
    {
        $annotation = new Annotation();
        $annotation->setDocument($document);
        $annotation->setAuthor($author);
        $annotation->setContent('Test annotation');
        return $annotation;
    }

    // === listDocuments tests ===

    public function testListDocumentsReturnsArray(): void
    {
        $session = $this->createSession();
        $document = $this->createDocument($session);

        $this->sessionRepository->method('find')->willReturn($session);
        $this->documentRepository->method('findBySession')->willReturn([$document]);
        $this->documentService->method('serialize')->willReturn(['id' => 'doc-id', 'title' => 'Test']);

        $result = $this->service->listDocuments($session->getId()->toString());

        $this->assertIsArray($result);
        $this->assertCount(1, $result);
    }

    public function testListDocumentsWithParentId(): void
    {
        $session = $this->createSession();
        $parentId = Uuid::v7()->toString();

        $this->sessionRepository->method('find')->willReturn($session);
        $this->documentRepository->expects($this->once())
            ->method('findBySession')
            ->with($session, $this->isInstanceOf(Uuid::class), null)
            ->willReturn([]);

        $this->service->listDocuments($session->getId()->toString(), $parentId);
    }

    public function testListDocumentsWithType(): void
    {
        $session = $this->createSession();

        $this->sessionRepository->method('find')->willReturn($session);
        $this->documentRepository->expects($this->once())
            ->method('findBySession')
            ->with($session, null, 'synthesis')
            ->willReturn([]);

        $this->service->listDocuments($session->getId()->toString(), null, 'synthesis');
    }

    public function testListDocumentsThrowsExceptionWhenSessionNotFound(): void
    {
        $this->sessionRepository->method('find')->willReturn(null);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Session non trouvée');

        $this->service->listDocuments(Uuid::v7()->toString());
    }

    // === readDocument tests ===

    public function testReadDocumentReturnsArray(): void
    {
        $session = $this->createSession();
        $document = $this->createDocument($session);

        $this->documentRepository->method('find')->willReturn($document);
        $this->documentService->method('serialize')->willReturn(['id' => 'doc-id', 'title' => 'Test']);

        $result = $this->service->readDocument($document->getId()->toString());

        $this->assertIsArray($result);
        $this->assertArrayHasKey('id', $result);
    }

    public function testReadDocumentWithAnnotations(): void
    {
        $session = $this->createSession();
        $document = $this->createDocument($session);
        $participant = $this->createParticipant($session);
        $annotation = $this->createAnnotation($document, $participant);

        $this->documentRepository->method('find')->willReturn($document);
        $this->documentService->method('serialize')->willReturn(['id' => 'doc-id']);
        $this->annotationRepository->method('findByDocumentWithFilters')->willReturn([$annotation]);
        $this->annotationService->method('serialize')->willReturn(['id' => 'ann-id']);

        $result = $this->service->readDocument($document->getId()->toString(), true);

        $this->assertArrayHasKey('annotations', $result);
        $this->assertCount(1, $result['annotations']);
    }

    public function testReadDocumentWithVersions(): void
    {
        $session = $this->createSession();
        $document = $this->createDocument($session);

        $this->documentRepository->method('find')->willReturn($document);
        $this->documentService->method('serialize')->willReturn(['id' => 'doc-id']);

        $result = $this->service->readDocument($document->getId()->toString(), false, true);

        $this->assertArrayHasKey('versions', $result);
    }

    public function testReadDocumentThrowsExceptionWhenNotFound(): void
    {
        $this->documentRepository->method('find')->willReturn(null);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Document non trouvé');

        $this->service->readDocument(Uuid::v7()->toString());
    }

    // === createDocument tests ===

    public function testCreateDocumentReturnsArray(): void
    {
        $session = $this->createSession();
        $document = $this->createDocument($session);

        $this->sessionRepository->method('find')->willReturn($session);
        $this->documentService->method('create')->willReturn($document);
        $this->documentService->method('serialize')->willReturn(['id' => 'doc-id', 'title' => 'Test']);

        $result = $this->service->createDocument(
            $session->getId()->toString(),
            ['title' => 'New Document']
        );

        $this->assertIsArray($result);
    }

    public function testCreateDocumentWithAuthor(): void
    {
        $session = $this->createSession();
        $document = $this->createDocument($session);
        $participant = $this->createParticipant($session);

        $this->sessionRepository->method('find')->willReturn($session);
        $this->participantRepository->method('find')->willReturn($participant);
        $this->documentService->expects($this->once())
            ->method('create')
            ->with($session, $this->isArray(), $participant)
            ->willReturn($document);
        $this->documentService->method('serialize')->willReturn(['id' => 'doc-id']);

        $this->service->createDocument(
            $session->getId()->toString(),
            ['title' => 'New Doc'],
            $participant->getId()->toString()
        );
    }

    public function testCreateDocumentWithoutAuthor(): void
    {
        $session = $this->createSession();
        $document = $this->createDocument($session);

        $this->sessionRepository->method('find')->willReturn($session);
        $this->documentService->expects($this->once())
            ->method('create')
            ->with($session, $this->isArray(), null)
            ->willReturn($document);
        $this->documentService->method('serialize')->willReturn(['id' => 'doc-id']);

        $this->service->createDocument($session->getId()->toString(), ['title' => 'Doc']);
    }

    public function testCreateDocumentThrowsExceptionWhenParticipantNotFound(): void
    {
        $session = $this->createSession();

        $this->sessionRepository->method('find')->willReturn($session);
        $this->participantRepository->method('find')->willReturn(null);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Participant non trouvé');

        $this->service->createDocument(
            $session->getId()->toString(),
            ['title' => 'Doc'],
            Uuid::v7()->toString()
        );
    }

    // === updateDocument tests ===

    public function testUpdateDocumentReturnsArray(): void
    {
        $session = $this->createSession();
        $document = $this->createDocument($session);

        $this->documentRepository->method('find')->willReturn($document);
        $this->documentService->method('update')->willReturn($document);
        $this->documentService->method('serialize')->willReturn(['id' => 'doc-id', 'title' => 'Updated']);

        $result = $this->service->updateDocument(
            $document->getId()->toString(),
            ['title' => 'Updated Title']
        );

        $this->assertIsArray($result);
    }

    public function testUpdateDocumentWithChangeDescription(): void
    {
        $session = $this->createSession();
        $document = $this->createDocument($session);

        $this->documentRepository->method('find')->willReturn($document);
        $this->documentService->expects($this->once())
            ->method('update')
            ->with(
                $document,
                $this->callback(fn($data) => !isset($data['change_description'])),
                null,
                'Version notes'
            )
            ->willReturn($document);
        $this->documentService->method('serialize')->willReturn(['id' => 'doc-id']);

        $this->service->updateDocument(
            $document->getId()->toString(),
            ['title' => 'New Title', 'change_description' => 'Version notes']
        );
    }

    public function testUpdateDocumentWithAuthor(): void
    {
        $session = $this->createSession();
        $document = $this->createDocument($session);
        $participant = $this->createParticipant($session);

        $this->documentRepository->method('find')->willReturn($document);
        $this->participantRepository->method('find')->willReturn($participant);
        $this->documentService->expects($this->once())
            ->method('update')
            ->with($document, $this->isArray(), $participant, null)
            ->willReturn($document);
        $this->documentService->method('serialize')->willReturn(['id' => 'doc-id']);

        $this->service->updateDocument(
            $document->getId()->toString(),
            ['content' => 'New content'],
            $participant->getId()->toString()
        );
    }

    // === deleteDocument tests ===

    public function testDeleteDocumentCallsService(): void
    {
        $session = $this->createSession();
        $document = $this->createDocument($session);

        $this->documentRepository->method('find')->willReturn($document);
        $this->documentService->expects($this->once())
            ->method('delete')
            ->with($document);

        $this->service->deleteDocument($document->getId()->toString());
    }

    public function testDeleteDocumentThrowsExceptionWhenNotFound(): void
    {
        $this->documentRepository->method('find')->willReturn(null);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Document non trouvé');

        $this->service->deleteDocument(Uuid::v7()->toString());
    }

    // === readAnnotations tests ===

    public function testReadAnnotationsReturnsArray(): void
    {
        $session = $this->createSession();
        $document = $this->createDocument($session);
        $participant = $this->createParticipant($session);
        $annotation = $this->createAnnotation($document, $participant);

        $this->documentRepository->method('find')->willReturn($document);
        $this->annotationRepository->method('findByDocumentWithFilters')->willReturn([$annotation]);
        $this->annotationService->method('serialize')->willReturn(['id' => 'ann-id']);

        $result = $this->service->readAnnotations($document->getId()->toString());

        $this->assertIsArray($result);
        $this->assertCount(1, $result);
    }

    public function testReadAnnotationsWithFilters(): void
    {
        $session = $this->createSession();
        $document = $this->createDocument($session);

        $filters = ['type' => 'question', 'status' => 'open'];

        $this->documentRepository->method('find')->willReturn($document);
        $this->annotationRepository->expects($this->once())
            ->method('findByDocumentWithFilters')
            ->with($document, $filters)
            ->willReturn([]);

        $this->service->readAnnotations($document->getId()->toString(), $filters);
    }

    // === getSessionAnnotations tests ===

    public function testGetSessionAnnotationsReturnsArray(): void
    {
        $session = $this->createSession();
        $document = $this->createDocument($session);
        $participant = $this->createParticipant($session);
        $annotation = $this->createAnnotation($document, $participant);

        $this->sessionRepository->method('find')->willReturn($session);
        $this->annotationRepository->method('findBySessionWithFilters')->willReturn([$annotation]);
        $this->annotationService->method('serialize')->willReturn(['id' => 'ann-id']);

        $result = $this->service->getSessionAnnotations($session->getId()->toString());

        $this->assertIsArray($result);
        $this->assertCount(1, $result);
    }

    public function testGetSessionAnnotationsWithFilters(): void
    {
        $session = $this->createSession();
        $filters = ['untreated_only' => true];

        $this->sessionRepository->method('find')->willReturn($session);
        $this->annotationRepository->expects($this->once())
            ->method('findBySessionWithFilters')
            ->with($session, $filters)
            ->willReturn([]);

        $this->service->getSessionAnnotations($session->getId()->toString(), $filters);
    }

    // === respondToAnnotation tests ===

    public function testRespondToAnnotationReturnsArray(): void
    {
        $session = $this->createSession();
        $document = $this->createDocument($session);
        $participant = $this->createParticipant($session);
        $annotation = $this->createAnnotation($document, $participant);
        $reply = $this->createAnnotation($document, $participant);

        $this->annotationRepository->method('find')->willReturn($annotation);
        $this->participantRepository->method('find')->willReturn($participant);
        $this->annotationService->method('createReply')->willReturn($reply);
        $this->annotationService->method('serialize')->willReturn(['id' => 'reply-id']);

        $result = $this->service->respondToAnnotation(
            $annotation->getId()->toString(),
            'Reply content',
            $participant->getId()->toString()
        );

        $this->assertIsArray($result);
    }

    public function testRespondToAnnotationRequiresAuthor(): void
    {
        $session = $this->createSession();
        $document = $this->createDocument($session);
        $participant = $this->createParticipant($session);
        $annotation = $this->createAnnotation($document, $participant);

        $this->annotationRepository->method('find')->willReturn($annotation);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Un auteur est requis pour répondre à une annotation');

        $this->service->respondToAnnotation($annotation->getId()->toString(), 'Content');
    }

    public function testRespondToAnnotationThrowsExceptionWhenAnnotationNotFound(): void
    {
        $this->annotationRepository->method('find')->willReturn(null);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Annotation non trouvée');

        $this->service->respondToAnnotation(
            Uuid::v7()->toString(),
            'Content',
            Uuid::v7()->toString()
        );
    }

    // === acknowledgeAnnotation tests ===

    public function testAcknowledgeAnnotationReturnsArray(): void
    {
        $session = $this->createSession();
        $document = $this->createDocument($session);
        $participant = $this->createParticipant($session);
        $annotation = $this->createAnnotation($document, $participant);

        $this->annotationRepository->method('find')->willReturn($annotation);
        $this->annotationService->method('markAsTakenIntoAccount')->willReturn($annotation);
        $this->annotationService->method('serialize')->willReturn(['id' => 'ann-id', 'status' => 'resolved']);

        $result = $this->service->acknowledgeAnnotation($annotation->getId()->toString());

        $this->assertIsArray($result);
    }

    public function testAcknowledgeAnnotationCallsService(): void
    {
        $session = $this->createSession();
        $document = $this->createDocument($session);
        $participant = $this->createParticipant($session);
        $annotation = $this->createAnnotation($document, $participant);

        $this->annotationRepository->method('find')->willReturn($annotation);
        $this->annotationService->expects($this->once())
            ->method('markAsTakenIntoAccount')
            ->with($annotation)
            ->willReturn($annotation);
        $this->annotationService->method('serialize')->willReturn([]);

        $this->service->acknowledgeAnnotation($annotation->getId()->toString());
    }

    // === getSessionStatus tests ===

    public function testGetSessionStatusReturnsArray(): void
    {
        $session = $this->createSession();

        $this->sessionRepository->method('find')->willReturn($session);
        $this->annotationRepository->method('countBySessionAndStatus')->willReturn(5);
        $this->annotationRepository->method('countUntreatedBySession')->willReturn(3);
        $this->decisionRepository->method('countPendingBySession')->willReturn(2);
        $this->participantRepository->method('findOnlineInSession')->willReturn([]);
        $this->decisionRepository->method('findBySession')->willReturn([]);
        $this->annotationRepository->method('findPriorityAnnotations')->willReturn([]);

        $result = $this->service->getSessionStatus($session->getId()->toString());

        $this->assertIsArray($result);
        $this->assertArrayHasKey('session', $result);
        $this->assertArrayHasKey('statistics', $result);
        $this->assertArrayHasKey('decisions', $result);
        $this->assertArrayHasKey('priority_annotations', $result);
    }

    public function testGetSessionStatusIncludesSessionInfo(): void
    {
        $session = $this->createSession();
        $session->setStatus(Session::STATUS_EN_COURS);

        $this->sessionRepository->method('find')->willReturn($session);
        $this->annotationRepository->method('countBySessionAndStatus')->willReturn(0);
        $this->annotationRepository->method('countUntreatedBySession')->willReturn(0);
        $this->decisionRepository->method('countPendingBySession')->willReturn(0);
        $this->participantRepository->method('findOnlineInSession')->willReturn([]);
        $this->decisionRepository->method('findBySession')->willReturn([]);
        $this->annotationRepository->method('findPriorityAnnotations')->willReturn([]);

        $result = $this->service->getSessionStatus($session->getId()->toString());

        $this->assertSame($session->getId()->toString(), $result['session']['id']);
        $this->assertSame('Test Session', $result['session']['title']);
        $this->assertSame(Session::STATUS_EN_COURS, $result['session']['status']);
    }

    public function testGetSessionStatusIncludesStatistics(): void
    {
        $session = $this->createSession();

        $this->sessionRepository->method('find')->willReturn($session);
        $this->annotationRepository->method('countBySessionAndStatus')->willReturn(10);
        $this->annotationRepository->method('countUntreatedBySession')->willReturn(5);
        $this->decisionRepository->method('countPendingBySession')->willReturn(3);
        $this->participantRepository->method('findOnlineInSession')->willReturn([
            new Participant(),
            new Participant(),
        ]);
        $this->decisionRepository->method('findBySession')->willReturn([]);
        $this->annotationRepository->method('findPriorityAnnotations')->willReturn([]);

        $result = $this->service->getSessionStatus($session->getId()->toString());

        $this->assertSame(10, $result['statistics']['open_annotations']);
        $this->assertSame(5, $result['statistics']['untreated_annotations']);
        $this->assertSame(3, $result['statistics']['pending_decisions']);
        $this->assertSame(2, $result['statistics']['online_participants']);
    }

    public function testGetSessionStatusIncludesDecisions(): void
    {
        $session = $this->createSession();
        $decision = new Decision();
        $decision->setSession($session);
        $decision->setTitle('Test Decision');

        $this->sessionRepository->method('find')->willReturn($session);
        $this->annotationRepository->method('countBySessionAndStatus')->willReturn(0);
        $this->annotationRepository->method('countUntreatedBySession')->willReturn(0);
        $this->decisionRepository->method('countPendingBySession')->willReturn(0);
        $this->participantRepository->method('findOnlineInSession')->willReturn([]);
        $this->decisionRepository->method('findBySession')->willReturn([$decision]);
        $this->decisionService->method('serialize')->willReturn(['id' => 'dec-id', 'title' => 'Test Decision']);
        $this->annotationRepository->method('findPriorityAnnotations')->willReturn([]);

        $result = $this->service->getSessionStatus($session->getId()->toString());

        $this->assertCount(1, $result['decisions']);
    }

    public function testGetSessionStatusIncludesPriorityAnnotations(): void
    {
        $session = $this->createSession();
        $document = $this->createDocument($session);
        $participant = $this->createParticipant($session);
        $annotation = $this->createAnnotation($document, $participant);

        $this->sessionRepository->method('find')->willReturn($session);
        $this->annotationRepository->method('countBySessionAndStatus')->willReturn(0);
        $this->annotationRepository->method('countUntreatedBySession')->willReturn(0);
        $this->decisionRepository->method('countPendingBySession')->willReturn(0);
        $this->participantRepository->method('findOnlineInSession')->willReturn([]);
        $this->decisionRepository->method('findBySession')->willReturn([]);
        $this->annotationRepository->expects($this->once())
            ->method('findPriorityAnnotations')
            ->with($session, 5)
            ->willReturn([$annotation]);
        $this->annotationService->method('serialize')->willReturn(['id' => 'ann-id']);

        $result = $this->service->getSessionStatus($session->getId()->toString());

        $this->assertCount(1, $result['priority_annotations']);
    }

    // === updateSessionStatus tests ===

    public function testUpdateSessionStatusReturnsArray(): void
    {
        $session = $this->createSession();

        $this->sessionRepository->method('find')->willReturn($session);
        $this->sessionService->method('updateStatus')->willReturn($session);

        $result = $this->service->updateSessionStatus(
            $session->getId()->toString(),
            Session::STATUS_EN_COURS
        );

        $this->assertIsArray($result);
        $this->assertArrayHasKey('id', $result);
        $this->assertArrayHasKey('title', $result);
        $this->assertArrayHasKey('status', $result);
        $this->assertArrayHasKey('updated_at', $result);
    }

    public function testUpdateSessionStatusWithValidStatus(): void
    {
        $session = $this->createSession();

        $this->sessionRepository->method('find')->willReturn($session);
        $this->sessionService->expects($this->once())
            ->method('updateStatus')
            ->with($session, Session::STATUS_TERMINE)
            ->willReturn($session);

        $this->service->updateSessionStatus(
            $session->getId()->toString(),
            Session::STATUS_TERMINE
        );
    }

    public function testUpdateSessionStatusAcceptsAllValidStatuses(): void
    {
        $validStatuses = [
            Session::STATUS_PREPARATION,
            Session::STATUS_EN_COURS,
            Session::STATUS_TERMINE,
            Session::STATUS_ARCHIVE,
        ];

        foreach ($validStatuses as $status) {
            $session = $this->createSession();

            $this->sessionRepository->method('find')->willReturn($session);
            $this->sessionService->method('updateStatus')->willReturn($session);

            $result = $this->service->updateSessionStatus(
                $session->getId()->toString(),
                $status
            );

            $this->assertIsArray($result);
        }
    }

    public function testUpdateSessionStatusThrowsExceptionForInvalidStatus(): void
    {
        $session = $this->createSession();

        $this->sessionRepository->method('find')->willReturn($session);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Statut invalide: invalid_status');

        $this->service->updateSessionStatus(
            $session->getId()->toString(),
            'invalid_status'
        );
    }

    public function testUpdateSessionStatusExceptionContainsValidStatuses(): void
    {
        $session = $this->createSession();

        $this->sessionRepository->method('find')->willReturn($session);

        try {
            $this->service->updateSessionStatus(
                $session->getId()->toString(),
                'wrong'
            );
            $this->fail('Expected exception not thrown');
        } catch (\InvalidArgumentException $e) {
            $this->assertStringContainsString('preparation', $e->getMessage());
            $this->assertStringContainsString('en_cours', $e->getMessage());
            $this->assertStringContainsString('termine', $e->getMessage());
            $this->assertStringContainsString('archive', $e->getMessage());
        }
    }

    // === Edge cases and error handling ===

    public function testInvalidUuidFormatThrowsException(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->service->listDocuments('not-a-valid-uuid');
    }

    public function testEmptyDocumentListReturnsEmptyArray(): void
    {
        $session = $this->createSession();

        $this->sessionRepository->method('find')->willReturn($session);
        $this->documentRepository->method('findBySession')->willReturn([]);

        $result = $this->service->listDocuments($session->getId()->toString());

        $this->assertSame([], $result);
    }

    public function testReadDocumentWithBothAnnotationsAndVersions(): void
    {
        $session = $this->createSession();
        $document = $this->createDocument($session);
        $participant = $this->createParticipant($session);
        $annotation = $this->createAnnotation($document, $participant);

        $this->documentRepository->method('find')->willReturn($document);
        $this->documentService->method('serialize')->willReturn(['id' => 'doc-id']);
        $this->annotationRepository->method('findByDocumentWithFilters')->willReturn([$annotation]);
        $this->annotationService->method('serialize')->willReturn(['id' => 'ann-id']);

        $result = $this->service->readDocument($document->getId()->toString(), true, true);

        $this->assertArrayHasKey('annotations', $result);
        $this->assertArrayHasKey('versions', $result);
    }

    public function testMultipleDocumentsAreSerializedIndividually(): void
    {
        $session = $this->createSession();
        $doc1 = $this->createDocument($session);
        $doc2 = $this->createDocument($session);
        $doc3 = $this->createDocument($session);

        $this->sessionRepository->method('find')->willReturn($session);
        $this->documentRepository->method('findBySession')->willReturn([$doc1, $doc2, $doc3]);

        $callCount = 0;
        $this->documentService->method('serialize')
            ->willReturnCallback(function () use (&$callCount) {
                $callCount++;
                return ['id' => 'doc-' . $callCount];
            });

        $result = $this->service->listDocuments($session->getId()->toString());

        $this->assertCount(3, $result);
        $this->assertSame(3, $callCount);
    }
}

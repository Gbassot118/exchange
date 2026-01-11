<?php

namespace App\Tests\Unit\Service\Document;

use App\Entity\Document;
use App\Entity\Participant;
use App\Entity\Session;
use App\Repository\DocumentRepository;
use App\Service\Document\DocumentService;
use App\Service\Mercure\MercurePublisher;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Symfony\Component\String\Slugger\AsciiSlugger;
use Symfony\Component\String\Slugger\SluggerInterface;
use Symfony\Component\Uid\Uuid;

class DocumentServiceTest extends TestCase
{
    private EntityManagerInterface&Stub $entityManager;
    private DocumentRepository&Stub $documentRepository;
    private SluggerInterface $slugger;
    private MercurePublisher&Stub $mercurePublisher;
    private DocumentService $service;

    protected function setUp(): void
    {
        $this->entityManager = $this->createStub(EntityManagerInterface::class);
        $this->documentRepository = $this->createStub(DocumentRepository::class);
        $this->slugger = new AsciiSlugger();
        $this->mercurePublisher = $this->createStub(MercurePublisher::class);

        $this->service = new DocumentService(
            $this->entityManager,
            $this->documentRepository,
            $this->slugger,
            $this->mercurePublisher
        );
    }

    private function createSession(): Session
    {
        $session = new Session();
        $session->setTitle('Test Session');
        return $session;
    }

    public function testCreateReturnsDocument(): void
    {
        $session = $this->createSession();

        $this->documentRepository->method('findOneBySessionAndSlug')->willReturn(null);
        $this->documentRepository->method('getMaxSortOrder')->willReturn(0);
        $this->entityManager->method('persist');
        $this->entityManager->method('flush');
        $this->mercurePublisher->method('publishDocumentCreated');

        $document = $this->service->create($session, ['title' => 'Test Doc']);

        $this->assertInstanceOf(Document::class, $document);
    }

    public function testCreateSetsTitle(): void
    {
        $session = $this->createSession();

        $this->documentRepository->method('findOneBySessionAndSlug')->willReturn(null);
        $this->documentRepository->method('getMaxSortOrder')->willReturn(0);
        $this->entityManager->method('persist');
        $this->entityManager->method('flush');
        $this->mercurePublisher->method('publishDocumentCreated');

        $document = $this->service->create($session, ['title' => 'My Document Title']);

        $this->assertSame('My Document Title', $document->getTitle());
    }

    public function testCreateGeneratesSlug(): void
    {
        $session = $this->createSession();

        $this->documentRepository->method('findOneBySessionAndSlug')->willReturn(null);
        $this->documentRepository->method('getMaxSortOrder')->willReturn(0);
        $this->entityManager->method('persist');
        $this->entityManager->method('flush');
        $this->mercurePublisher->method('publishDocumentCreated');

        $document = $this->service->create($session, ['title' => 'My Document Title']);

        $this->assertSame('my-document-title', $document->getSlug());
    }

    public function testCreateSetsContent(): void
    {
        $session = $this->createSession();

        $this->documentRepository->method('findOneBySessionAndSlug')->willReturn(null);
        $this->documentRepository->method('getMaxSortOrder')->willReturn(0);
        $this->entityManager->method('persist');
        $this->entityManager->method('flush');
        $this->mercurePublisher->method('publishDocumentCreated');

        $document = $this->service->create($session, [
            'title' => 'Test',
            'content' => 'Document content here',
        ]);

        $this->assertSame('Document content here', $document->getContent());
    }

    public function testCreateSetsType(): void
    {
        $session = $this->createSession();

        $this->documentRepository->method('findOneBySessionAndSlug')->willReturn(null);
        $this->documentRepository->method('getMaxSortOrder')->willReturn(0);
        $this->entityManager->method('persist');
        $this->entityManager->method('flush');
        $this->mercurePublisher->method('publishDocumentCreated');

        $document = $this->service->create($session, [
            'title' => 'Test',
            'type' => Document::TYPE_SYNTHESIS,
        ]);

        $this->assertSame(Document::TYPE_SYNTHESIS, $document->getType());
    }

    public function testCreateSetsDefaultType(): void
    {
        $session = $this->createSession();

        $this->documentRepository->method('findOneBySessionAndSlug')->willReturn(null);
        $this->documentRepository->method('getMaxSortOrder')->willReturn(0);
        $this->entityManager->method('persist');
        $this->entityManager->method('flush');
        $this->mercurePublisher->method('publishDocumentCreated');

        $document = $this->service->create($session, ['title' => 'Test']);

        $this->assertSame(Document::TYPE_GENERAL, $document->getType());
    }

    public function testCreateSetsMetadata(): void
    {
        $session = $this->createSession();
        $metadata = ['key' => 'value'];

        $this->documentRepository->method('findOneBySessionAndSlug')->willReturn(null);
        $this->documentRepository->method('getMaxSortOrder')->willReturn(0);
        $this->entityManager->method('persist');
        $this->entityManager->method('flush');
        $this->mercurePublisher->method('publishDocumentCreated');

        $document = $this->service->create($session, [
            'title' => 'Test',
            'metadata' => $metadata,
        ]);

        $this->assertSame($metadata, $document->getMetadata());
    }

    public function testCreateSetsParent(): void
    {
        $session = $this->createSession();

        $parent = new Document();
        $parent->setSession($session);
        $parent->setTitle('Parent');
        $parent->setSlug('parent');

        $this->documentRepository->method('findOneBySessionAndSlug')->willReturn(null);
        $this->documentRepository->method('find')->willReturn($parent);
        $this->documentRepository->method('getMaxSortOrder')->willReturn(0);
        $this->entityManager->method('persist');
        $this->entityManager->method('flush');
        $this->mercurePublisher->method('publishDocumentCreated');

        $document = $this->service->create($session, [
            'title' => 'Child',
            'parent_id' => $parent->getId()->toString(),
        ]);

        $this->assertSame($parent, $document->getParent());
    }

    public function testCreateWithInvalidParentId(): void
    {
        $session = $this->createSession();

        $this->documentRepository->method('findOneBySessionAndSlug')->willReturn(null);
        $this->documentRepository->method('find')->willReturn(null);
        $this->documentRepository->method('getMaxSortOrder')->willReturn(0);
        $this->entityManager->method('persist');
        $this->entityManager->method('flush');
        $this->mercurePublisher->method('publishDocumentCreated');

        $document = $this->service->create($session, [
            'title' => 'Orphan',
            'parent_id' => Uuid::v7()->toString(),
        ]);

        $this->assertNull($document->getParent());
    }

    public function testCreateCalculatesNextSortOrder(): void
    {
        $session = $this->createSession();

        $this->documentRepository->method('findOneBySessionAndSlug')->willReturn(null);
        $this->documentRepository->method('getMaxSortOrder')->willReturn(5);
        $this->entityManager->method('persist');
        $this->entityManager->method('flush');
        $this->mercurePublisher->method('publishDocumentCreated');

        $document = $this->service->create($session, ['title' => 'Test']);

        $this->assertSame(6, $document->getSortOrder());
    }

    public function testCreateInitialVersion(): void
    {
        $session = $this->createSession();

        $this->documentRepository->method('findOneBySessionAndSlug')->willReturn(null);
        $this->documentRepository->method('getMaxSortOrder')->willReturn(0);
        $this->entityManager->method('persist');
        $this->entityManager->method('flush');
        $this->mercurePublisher->method('publishDocumentCreated');

        $document = $this->service->create($session, ['title' => 'Test', 'content' => 'Content']);

        $this->assertCount(1, $document->getVersions());
        $this->assertSame(1, $document->getVersions()->first()->getVersion());
    }

    public function testCreatePublishesMercureEvent(): void
    {
        $session = $this->createSession();

        $this->documentRepository->method('findOneBySessionAndSlug')->willReturn(null);
        $this->documentRepository->method('getMaxSortOrder')->willReturn(0);
        $this->entityManager->method('persist');
        $this->entityManager->method('flush');

        $mercurePublisher = $this->createMock(MercurePublisher::class);
        $mercurePublisher->expects($this->once())
            ->method('publishDocumentCreated')
            ->with($session->getId()->toString(), $this->isArray());

        $service = new DocumentService(
            $this->entityManager,
            $this->documentRepository,
            $this->slugger,
            $mercurePublisher
        );

        $service->create($session, ['title' => 'Test']);
    }

    public function testUpdateModifiesTitle(): void
    {
        $session = $this->createSession();
        $document = new Document();
        $document->setSession($session);
        $document->setTitle('Old Title');
        $document->setSlug('old-title');
        $document->setContent('content');

        $this->entityManager->method('flush');
        $this->mercurePublisher->method('publishDocumentUpdated');

        $result = $this->service->update($document, ['title' => 'New Title']);

        $this->assertSame('New Title', $result->getTitle());
    }

    public function testUpdateModifiesContent(): void
    {
        $session = $this->createSession();
        $document = new Document();
        $document->setSession($session);
        $document->setTitle('Doc');
        $document->setSlug('doc');
        $document->setContent('Old content');

        $this->entityManager->method('persist');
        $this->entityManager->method('flush');
        $this->mercurePublisher->method('publishDocumentUpdated');

        $result = $this->service->update($document, ['content' => 'New content']);

        $this->assertSame('New content', $result->getContent());
    }

    public function testUpdateIncrementsVersionOnContentChange(): void
    {
        $session = $this->createSession();
        $document = new Document();
        $document->setSession($session);
        $document->setTitle('Doc');
        $document->setSlug('doc');
        $document->setContent('Old content');

        $this->entityManager->method('persist');
        $this->entityManager->method('flush');
        $this->mercurePublisher->method('publishDocumentUpdated');

        $oldVersion = $document->getCurrentVersion();
        $this->service->update($document, ['content' => 'New content']);

        $this->assertSame($oldVersion + 1, $document->getCurrentVersion());
    }

    public function testUpdateDoesNotIncrementVersionWhenNoContentChange(): void
    {
        $session = $this->createSession();
        $document = new Document();
        $document->setSession($session);
        $document->setTitle('Doc');
        $document->setSlug('doc');
        $document->setContent('Same content');

        $this->entityManager->method('flush');
        $this->mercurePublisher->method('publishDocumentUpdated');

        $oldVersion = $document->getCurrentVersion();
        $this->service->update($document, ['title' => 'New Title']);

        $this->assertSame($oldVersion, $document->getCurrentVersion());
    }

    public function testUpdateCreatesVersionRecord(): void
    {
        $session = $this->createSession();
        $document = new Document();
        $document->setSession($session);
        $document->setTitle('Doc');
        $document->setSlug('doc');
        $document->setContent('Old content');

        $this->entityManager->method('persist');
        $this->entityManager->method('flush');
        $this->mercurePublisher->method('publishDocumentUpdated');

        $initialVersionCount = $document->getVersions()->count();
        $this->service->update($document, ['content' => 'New content']);

        $this->assertSame($initialVersionCount + 1, $document->getVersions()->count());
    }

    public function testUpdatePublishesMercureEvent(): void
    {
        $session = $this->createSession();
        $document = new Document();
        $document->setSession($session);
        $document->setTitle('Doc');
        $document->setSlug('doc');
        $document->setContent('content');

        $this->entityManager->method('flush');

        $mercurePublisher = $this->createMock(MercurePublisher::class);
        $mercurePublisher->expects($this->once())
            ->method('publishDocumentUpdated')
            ->with(
                $session->getId()->toString(),
                $document->getId()->toString(),
                $this->isArray()
            );

        $service = new DocumentService(
            $this->entityManager,
            $this->documentRepository,
            $this->slugger,
            $mercurePublisher
        );

        $service->update($document, ['title' => 'New']);
    }

    public function testUpdateWithAuthor(): void
    {
        $session = $this->createSession();
        $author = new Participant();
        $author->setSession($session);
        $author->setPseudo('Author');

        $document = new Document();
        $document->setSession($session);
        $document->setTitle('Doc');
        $document->setSlug('doc');
        $document->setContent('Old');

        $this->entityManager->method('persist');
        $this->entityManager->method('flush');
        $this->mercurePublisher->method('publishDocumentUpdated');

        $this->service->update($document, ['content' => 'New'], $author);

        $latestVersion = $document->getVersions()->last();
        $this->assertSame($author, $latestVersion->getAuthor());
    }

    public function testDeleteRemovesDocument(): void
    {
        $session = $this->createSession();
        $document = new Document();
        $document->setSession($session);
        $document->setTitle('Doc');
        $document->setSlug('doc');

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects($this->once())
            ->method('remove')
            ->with($document);
        $entityManager->method('flush');

        $this->mercurePublisher->method('publishDocumentDeleted');

        $service = new DocumentService(
            $entityManager,
            $this->documentRepository,
            $this->slugger,
            $this->mercurePublisher
        );

        $service->delete($document);
    }

    public function testDeleteRemovesChildren(): void
    {
        $session = $this->createSession();

        $parent = new Document();
        $parent->setSession($session);
        $parent->setTitle('Parent');
        $parent->setSlug('parent');

        $child = new Document();
        $child->setSession($session);
        $child->setTitle('Child');
        $child->setSlug('child');
        $parent->addChild($child);

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects($this->exactly(2))->method('remove');
        $entityManager->method('flush');

        $this->mercurePublisher->method('publishDocumentDeleted');

        $service = new DocumentService(
            $entityManager,
            $this->documentRepository,
            $this->slugger,
            $this->mercurePublisher
        );

        $service->delete($parent);
    }

    public function testDeletePublishesMercureEvent(): void
    {
        $session = $this->createSession();
        $document = new Document();
        $document->setSession($session);
        $document->setTitle('Doc');
        $document->setSlug('doc');

        $this->entityManager->method('remove');
        $this->entityManager->method('flush');

        $mercurePublisher = $this->createMock(MercurePublisher::class);
        $mercurePublisher->expects($this->once())
            ->method('publishDocumentDeleted')
            ->with($session->getId()->toString(), $document->getId()->toString());

        $service = new DocumentService(
            $this->entityManager,
            $this->documentRepository,
            $this->slugger,
            $mercurePublisher
        );

        $service->delete($document);
    }

    public function testReorderSamePositionNoChange(): void
    {
        $session = $this->createSession();
        $document = new Document();
        $document->setSession($session);
        $document->setTitle('Doc');
        $document->setSlug('doc');
        $document->setSortOrder(3);

        $this->documentRepository->method('findBySession')->willReturn([]);

        $result = $this->service->reorder($document, 3);

        $this->assertSame(3, $result->getSortOrder());
    }

    public function testSerializeIncludesAllFields(): void
    {
        $session = $this->createSession();
        $document = new Document();
        $document->setSession($session);
        $document->setTitle('Test Doc');
        $document->setSlug('test-doc');
        $document->setContent('Content here');
        $document->setType(Document::TYPE_SYNTHESIS);
        $document->setSortOrder(5);

        $serialized = $this->service->serialize($document);

        $this->assertArrayHasKey('id', $serialized);
        $this->assertArrayHasKey('title', $serialized);
        $this->assertArrayHasKey('slug', $serialized);
        $this->assertArrayHasKey('type', $serialized);
        $this->assertArrayHasKey('content', $serialized);
        $this->assertArrayHasKey('sort_order', $serialized);
        $this->assertArrayHasKey('current_version', $serialized);
        $this->assertArrayHasKey('created_at', $serialized);
        $this->assertArrayHasKey('updated_at', $serialized);
    }

    public function testSerializeWithoutContent(): void
    {
        $session = $this->createSession();
        $document = new Document();
        $document->setSession($session);
        $document->setTitle('Test');
        $document->setSlug('test');
        $document->setContent('Secret content');

        $serialized = $this->service->serialize($document, false);

        $this->assertArrayNotHasKey('content', $serialized);
        $this->assertArrayNotHasKey('metadata', $serialized);
    }

    public function testSlugConflictResolution(): void
    {
        $session = $this->createSession();

        $existingDoc = new Document();
        $existingDoc->setSession($session);
        $existingDoc->setTitle('Test');
        $existingDoc->setSlug('test');

        // First call returns existing, subsequent calls return null
        $this->documentRepository->method('findOneBySessionAndSlug')
            ->willReturnCallback(function ($s, $slug) use ($existingDoc) {
                return $slug === 'test' ? $existingDoc : null;
            });
        $this->documentRepository->method('getMaxSortOrder')->willReturn(0);
        $this->entityManager->method('persist');
        $this->entityManager->method('flush');
        $this->mercurePublisher->method('publishDocumentCreated');

        $document = $this->service->create($session, ['title' => 'Test']);

        $this->assertSame('test-1', $document->getSlug());
    }

    public function testCreateWithEmptyContent(): void
    {
        $session = $this->createSession();

        $this->documentRepository->method('findOneBySessionAndSlug')->willReturn(null);
        $this->documentRepository->method('getMaxSortOrder')->willReturn(0);
        $this->entityManager->method('persist');
        $this->entityManager->method('flush');
        $this->mercurePublisher->method('publishDocumentCreated');

        $document = $this->service->create($session, ['title' => 'Empty Doc']);

        $this->assertSame('', $document->getContent());
    }

    public function testDeleteRecursiveChildren(): void
    {
        $session = $this->createSession();

        $root = new Document();
        $root->setSession($session);
        $root->setTitle('Root');
        $root->setSlug('root');

        $child1 = new Document();
        $child1->setSession($session);
        $child1->setTitle('Child 1');
        $child1->setSlug('child-1');
        $root->addChild($child1);

        $child2 = new Document();
        $child2->setSession($session);
        $child2->setTitle('Child 2');
        $child2->setSlug('child-2');
        $child1->addChild($child2);

        $child3 = new Document();
        $child3->setSession($session);
        $child3->setTitle('Child 3');
        $child3->setSlug('child-3');
        $child2->addChild($child3);

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects($this->exactly(4))->method('remove');
        $entityManager->method('flush');

        $this->mercurePublisher->method('publishDocumentDeleted');

        $service = new DocumentService(
            $entityManager,
            $this->documentRepository,
            $this->slugger,
            $this->mercurePublisher
        );

        $service->delete($root);
    }

    public function testUpdateAgentDefaultDescription(): void
    {
        $session = $this->createSession();

        $agent = new Participant();
        $agent->setSession($session);
        $agent->setPseudo('AI Agent');
        $agent->setIsAgent(true);

        $document = new Document();
        $document->setSession($session);
        $document->setTitle('Doc');
        $document->setSlug('doc');
        $document->setContent('Old');

        $this->entityManager->method('persist');
        $this->entityManager->method('flush');
        $this->mercurePublisher->method('publishDocumentUpdated');

        $this->service->update($document, ['content' => 'New'], $agent);

        $latestVersion = $document->getVersions()->last();
        $this->assertSame('Mise à jour par l\'agent IA', $latestVersion->getChangeDescription());
    }

    public function testUpdateWithCustomChangeDescription(): void
    {
        $session = $this->createSession();

        $document = new Document();
        $document->setSession($session);
        $document->setTitle('Doc');
        $document->setSlug('doc');
        $document->setContent('Old');

        $this->entityManager->method('persist');
        $this->entityManager->method('flush');
        $this->mercurePublisher->method('publishDocumentUpdated');

        $this->service->update($document, ['content' => 'New'], null, 'Custom change description');

        $latestVersion = $document->getVersions()->last();
        $this->assertSame('Custom change description', $latestVersion->getChangeDescription());
    }
}

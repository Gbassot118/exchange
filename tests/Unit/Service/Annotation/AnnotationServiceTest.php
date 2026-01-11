<?php

namespace App\Tests\Unit\Service\Annotation;

use App\Entity\Annotation;
use App\Entity\Document;
use App\Entity\Participant;
use App\Entity\Session;
use App\Repository\AnnotationRepository;
use App\Repository\ParticipantRepository;
use App\Service\Annotation\AnnotationService;
use App\Service\Mercure\MercurePublisher;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;

class AnnotationServiceTest extends TestCase
{
    private EntityManagerInterface&Stub $entityManager;
    private AnnotationRepository&Stub $annotationRepository;
    private ParticipantRepository&Stub $participantRepository;
    private MercurePublisher&Stub $mercurePublisher;
    private AnnotationService $service;

    protected function setUp(): void
    {
        $this->entityManager = $this->createStub(EntityManagerInterface::class);
        $this->annotationRepository = $this->createStub(AnnotationRepository::class);
        $this->participantRepository = $this->createStub(ParticipantRepository::class);
        $this->mercurePublisher = $this->createStub(MercurePublisher::class);

        $this->service = new AnnotationService(
            $this->entityManager,
            $this->annotationRepository,
            $this->participantRepository,
            $this->mercurePublisher
        );
    }

    private function createTestDocument(): Document
    {
        $session = new Session();
        $session->setTitle('Test Session');

        $document = new Document();
        $document->setSession($session);
        $document->setTitle('Test Doc');
        $document->setSlug('test-doc');

        return $document;
    }

    private function createTestParticipant(Session $session): Participant
    {
        $participant = new Participant();
        $participant->setSession($session);
        $participant->setPseudo('TestUser');
        return $participant;
    }

    public function testCreateReturnsAnnotation(): void
    {
        $document = $this->createTestDocument();
        $author = $this->createTestParticipant($document->getSession());

        $annotation = $this->service->create($document, $author, 'Test content');

        $this->assertInstanceOf(Annotation::class, $annotation);
    }

    public function testCreateSetsDocument(): void
    {
        $document = $this->createTestDocument();
        $author = $this->createTestParticipant($document->getSession());

        $annotation = $this->service->create($document, $author, 'Test content');

        $this->assertSame($document, $annotation->getDocument());
    }

    public function testCreateSetsAuthor(): void
    {
        $document = $this->createTestDocument();
        $author = $this->createTestParticipant($document->getSession());

        $annotation = $this->service->create($document, $author, 'Test content');

        $this->assertSame($author, $annotation->getAuthor());
    }

    public function testCreateSetsContent(): void
    {
        $document = $this->createTestDocument();
        $author = $this->createTestParticipant($document->getSession());

        $annotation = $this->service->create($document, $author, 'My annotation content');

        $this->assertSame('My annotation content', $annotation->getContent());
    }

    public function testCreateSetsType(): void
    {
        $document = $this->createTestDocument();
        $author = $this->createTestParticipant($document->getSession());

        $annotation = $this->service->create($document, $author, 'Test', Annotation::TYPE_COMMENT);

        $this->assertSame(Annotation::TYPE_COMMENT, $annotation->getType());
    }

    public function testCreateSetsAnchor(): void
    {
        $document = $this->createTestDocument();
        $author = $this->createTestParticipant($document->getSession());
        $anchor = ['start' => 10, 'end' => 50];

        $annotation = $this->service->create($document, $author, 'Test', Annotation::TYPE_COMMENT, $anchor);

        $this->assertSame($anchor, $annotation->getAnchor());
    }

    public function testCreateExtractsMentions(): void
    {
        $document = $this->createTestDocument();
        $session = $document->getSession();

        $author = $this->createTestParticipant($session);

        $mentionedUser = new Participant();
        $mentionedUser->setSession($session);
        $mentionedUser->setPseudo('JohnDoe');

        $this->participantRepository->method('findBySessionAndPseudo')
            ->with($session, 'JohnDoe')
            ->willReturn($mentionedUser);

        $annotation = $this->service->create($document, $author, 'Hello @JohnDoe!');

        $this->assertContains($mentionedUser->getId()->toString(), $annotation->getMentions());
    }

    public function testCreatePublishesMercureEvent(): void
    {
        $document = $this->createTestDocument();
        $author = $this->createTestParticipant($document->getSession());

        $mercurePublisher = $this->createMock(MercurePublisher::class);
        $mercurePublisher->expects($this->once())
            ->method('publishAnnotationCreated')
            ->with(
                $document->getSession()->getId()->toString(),
                $document->getId()->toString(),
                $this->isArray()
            );

        $service = new AnnotationService(
            $this->entityManager,
            $this->annotationRepository,
            $this->participantRepository,
            $mercurePublisher
        );

        $service->create($document, $author, 'Test');
    }

    public function testCreateReplyReturnsAnnotation(): void
    {
        $document = $this->createTestDocument();
        $author = $this->createTestParticipant($document->getSession());

        $parent = new Annotation();
        $parent->setDocument($document);
        $parent->setAuthor($author);
        $parent->setContent('Parent content');

        $reply = $this->service->createReply($parent, 'Reply content', $author);

        $this->assertInstanceOf(Annotation::class, $reply);
    }

    public function testCreateReplySetsParentAnnotation(): void
    {
        $document = $this->createTestDocument();
        $author = $this->createTestParticipant($document->getSession());

        $parent = new Annotation();
        $parent->setDocument($document);
        $parent->setAuthor($author);
        $parent->setContent('Parent content');

        $reply = $this->service->createReply($parent, 'Reply content', $author);

        $this->assertSame($parent, $reply->getParentAnnotation());
    }

    public function testCreateReplyInheritsDocument(): void
    {
        $document = $this->createTestDocument();
        $author = $this->createTestParticipant($document->getSession());

        $parent = new Annotation();
        $parent->setDocument($document);
        $parent->setAuthor($author);
        $parent->setContent('Parent content');

        $reply = $this->service->createReply($parent, 'Reply content', $author);

        $this->assertSame($document, $reply->getDocument());
    }

    public function testCreateReplyTypeIsComment(): void
    {
        $document = $this->createTestDocument();
        $author = $this->createTestParticipant($document->getSession());

        $parent = new Annotation();
        $parent->setDocument($document);
        $parent->setAuthor($author);
        $parent->setContent('Parent content');

        $reply = $this->service->createReply($parent, 'Reply', $author);

        $this->assertSame(Annotation::TYPE_COMMENT, $reply->getType());
    }

    public function testUpdateModifiesContent(): void
    {
        $document = $this->createTestDocument();
        $author = $this->createTestParticipant($document->getSession());

        $annotation = new Annotation();
        $annotation->setDocument($document);
        $annotation->setAuthor($author);
        $annotation->setContent('Old content');

        $result = $this->service->update($annotation, 'New content');

        $this->assertSame('New content', $result->getContent());
    }

    public function testUpdateReextractsMentions(): void
    {
        $document = $this->createTestDocument();
        $session = $document->getSession();
        $author = $this->createTestParticipant($session);

        $annotation = new Annotation();
        $annotation->setDocument($document);
        $annotation->setAuthor($author);
        $annotation->setContent('Old content');
        $annotation->setMentions([]);

        $newUser = new Participant();
        $newUser->setSession($session);
        $newUser->setPseudo('NewUser');

        $this->participantRepository->method('findBySessionAndPseudo')
            ->with($session, 'NewUser')
            ->willReturn($newUser);

        $result = $this->service->update($annotation, 'Mentioning @NewUser now');

        $this->assertContains($newUser->getId()->toString(), $result->getMentions());
    }

    public function testUpdatePublishesMercureEvent(): void
    {
        $document = $this->createTestDocument();
        $author = $this->createTestParticipant($document->getSession());

        $annotation = new Annotation();
        $annotation->setDocument($document);
        $annotation->setAuthor($author);
        $annotation->setContent('Content');

        $mercurePublisher = $this->createMock(MercurePublisher::class);
        $mercurePublisher->expects($this->once())
            ->method('publishAnnotationUpdated');

        $service = new AnnotationService(
            $this->entityManager,
            $this->annotationRepository,
            $this->participantRepository,
            $mercurePublisher
        );

        $service->update($annotation, 'New content');
    }

    public function testResolveChangesStatus(): void
    {
        $document = $this->createTestDocument();
        $author = $this->createTestParticipant($document->getSession());
        $resolver = $this->createTestParticipant($document->getSession());

        $annotation = new Annotation();
        $annotation->setDocument($document);
        $annotation->setAuthor($author);
        $annotation->setContent('Content');

        $result = $this->service->resolve($annotation, $resolver);

        $this->assertSame(Annotation::STATUS_RESOLVED, $result->getStatus());
    }

    public function testResolveSetsResolvedBy(): void
    {
        $document = $this->createTestDocument();
        $author = $this->createTestParticipant($document->getSession());
        $resolver = $this->createTestParticipant($document->getSession());

        $annotation = new Annotation();
        $annotation->setDocument($document);
        $annotation->setAuthor($author);
        $annotation->setContent('Content');

        $result = $this->service->resolve($annotation, $resolver);

        $this->assertSame($resolver, $result->getResolvedBy());
    }

    public function testResolvePublishesMercureEvent(): void
    {
        $document = $this->createTestDocument();
        $author = $this->createTestParticipant($document->getSession());
        $resolver = $this->createTestParticipant($document->getSession());

        $annotation = new Annotation();
        $annotation->setDocument($document);
        $annotation->setAuthor($author);
        $annotation->setContent('Content');

        $mercurePublisher = $this->createMock(MercurePublisher::class);
        $mercurePublisher->expects($this->once())
            ->method('publishAnnotationResolved');

        $service = new AnnotationService(
            $this->entityManager,
            $this->annotationRepository,
            $this->participantRepository,
            $mercurePublisher
        );

        $service->resolve($annotation, $resolver);
    }

    public function testMarkAsTakenIntoAccountSetsFlag(): void
    {
        $document = $this->createTestDocument();
        $author = $this->createTestParticipant($document->getSession());

        $annotation = new Annotation();
        $annotation->setDocument($document);
        $annotation->setAuthor($author);
        $annotation->setContent('Content');

        $result = $this->service->markAsTakenIntoAccount($annotation);

        $this->assertTrue($result->isTakenIntoAccount());
    }

    public function testMarkAsTakenIntoAccountPublishesMercureEvent(): void
    {
        $document = $this->createTestDocument();
        $author = $this->createTestParticipant($document->getSession());

        $annotation = new Annotation();
        $annotation->setDocument($document);
        $annotation->setAuthor($author);
        $annotation->setContent('Content');

        $mercurePublisher = $this->createMock(MercurePublisher::class);
        $mercurePublisher->expects($this->once())
            ->method('publishAnnotationUpdated');

        $service = new AnnotationService(
            $this->entityManager,
            $this->annotationRepository,
            $this->participantRepository,
            $mercurePublisher
        );

        $service->markAsTakenIntoAccount($annotation);
    }

    public function testSetStatusChangesStatus(): void
    {
        $document = $this->createTestDocument();
        $author = $this->createTestParticipant($document->getSession());

        $annotation = new Annotation();
        $annotation->setDocument($document);
        $annotation->setAuthor($author);
        $annotation->setContent('Content');

        $result = $this->service->setStatus($annotation, Annotation::STATUS_IN_PROGRESS);

        $this->assertSame(Annotation::STATUS_IN_PROGRESS, $result->getStatus());
    }

    public function testSerializeIncludesAllFields(): void
    {
        $document = $this->createTestDocument();
        $author = $this->createTestParticipant($document->getSession());

        $annotation = new Annotation();
        $annotation->setDocument($document);
        $annotation->setAuthor($author);
        $annotation->setContent('Test content');

        $serialized = $this->service->serialize($annotation);

        $this->assertArrayHasKey('id', $serialized);
        $this->assertArrayHasKey('content', $serialized);
        $this->assertArrayHasKey('type', $serialized);
        $this->assertArrayHasKey('status', $serialized);
        $this->assertArrayHasKey('anchor', $serialized);
        $this->assertArrayHasKey('author', $serialized);
        $this->assertArrayHasKey('document_id', $serialized);
        $this->assertArrayHasKey('mentions', $serialized);
        $this->assertArrayHasKey('taken_into_account', $serialized);
        $this->assertArrayHasKey('created_at', $serialized);
        $this->assertArrayHasKey('reply_count', $serialized);
    }

    public function testSerializeIncludesAuthorInfo(): void
    {
        $document = $this->createTestDocument();
        $author = $this->createTestParticipant($document->getSession());
        $author->setPseudo('AuthorName');

        $annotation = new Annotation();
        $annotation->setDocument($document);
        $annotation->setAuthor($author);
        $annotation->setContent('Test');

        $serialized = $this->service->serialize($annotation);

        $this->assertArrayHasKey('id', $serialized['author']);
        $this->assertArrayHasKey('pseudo', $serialized['author']);
        $this->assertArrayHasKey('color', $serialized['author']);
        $this->assertArrayHasKey('is_agent', $serialized['author']);
        $this->assertSame('AuthorName', $serialized['author']['pseudo']);
    }

    public function testSerializeIncludesReplyCount(): void
    {
        $document = $this->createTestDocument();
        $author = $this->createTestParticipant($document->getSession());

        $annotation = new Annotation();
        $annotation->setDocument($document);
        $annotation->setAuthor($author);
        $annotation->setContent('Parent');

        $reply1 = new Annotation();
        $reply1->setDocument($document);
        $reply1->setAuthor($author);
        $reply1->setContent('Reply 1');
        $annotation->addReply($reply1);

        $reply2 = new Annotation();
        $reply2->setDocument($document);
        $reply2->setAuthor($author);
        $reply2->setContent('Reply 2');
        $annotation->addReply($reply2);

        $serialized = $this->service->serialize($annotation);

        $this->assertSame(2, $serialized['reply_count']);
    }

    public function testExtractMentionsWithValidPseudos(): void
    {
        $document = $this->createTestDocument();
        $session = $document->getSession();
        $author = $this->createTestParticipant($session);

        $user1 = new Participant();
        $user1->setSession($session);
        $user1->setPseudo('User1');

        $user2 = new Participant();
        $user2->setSession($session);
        $user2->setPseudo('User2');

        $this->participantRepository->method('findBySessionAndPseudo')
            ->willReturnCallback(function ($s, $pseudo) use ($user1, $user2) {
                if ($pseudo === 'User1') return $user1;
                if ($pseudo === 'User2') return $user2;
                return null;
            });

        $annotation = $this->service->create($document, $author, 'Hello @User1 and @User2!');

        $this->assertCount(2, $annotation->getMentions());
    }

    public function testExtractMentionsWithInvalidPseudos(): void
    {
        $document = $this->createTestDocument();
        $author = $this->createTestParticipant($document->getSession());

        $this->participantRepository->method('findBySessionAndPseudo')->willReturn(null);

        $annotation = $this->service->create($document, $author, 'Hello @NonExistent!');

        $this->assertEmpty($annotation->getMentions());
    }

    public function testExtractMentionsReturnsUniqueIds(): void
    {
        $document = $this->createTestDocument();
        $session = $document->getSession();
        $author = $this->createTestParticipant($session);

        $user = new Participant();
        $user->setSession($session);
        $user->setPseudo('User');

        $this->participantRepository->method('findBySessionAndPseudo')
            ->with($session, 'User')
            ->willReturn($user);

        $annotation = $this->service->create($document, $author, '@User @User @User');

        // Should have only 1 unique mention
        $this->assertCount(1, $annotation->getMentions());
    }

    public function testExtractMentionsWithNoMentions(): void
    {
        $document = $this->createTestDocument();
        $author = $this->createTestParticipant($document->getSession());

        $annotation = $this->service->create($document, $author, 'No mentions here');

        $this->assertEmpty($annotation->getMentions());
    }

    public function testSerializeWithNullParent(): void
    {
        $document = $this->createTestDocument();
        $author = $this->createTestParticipant($document->getSession());

        $annotation = new Annotation();
        $annotation->setDocument($document);
        $annotation->setAuthor($author);
        $annotation->setContent('Root annotation');

        $serialized = $this->service->serialize($annotation);

        $this->assertNull($serialized['parent_id']);
    }

    public function testSerializeWithNullResolvedBy(): void
    {
        $document = $this->createTestDocument();
        $author = $this->createTestParticipant($document->getSession());

        $annotation = new Annotation();
        $annotation->setDocument($document);
        $annotation->setAuthor($author);
        $annotation->setContent('Unresolved');

        $serialized = $this->service->serialize($annotation);

        $this->assertNull($serialized['resolved_by']);
    }

    public function testCreateReplyToReply(): void
    {
        $document = $this->createTestDocument();
        $author = $this->createTestParticipant($document->getSession());

        $root = new Annotation();
        $root->setDocument($document);
        $root->setAuthor($author);
        $root->setContent('Root');

        $reply1 = $this->service->createReply($root, 'Reply to root', $author);
        $this->assertTrue($reply1->isReply());
    }
}

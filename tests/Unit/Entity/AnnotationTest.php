<?php

namespace App\Tests\Unit\Entity;

use App\Entity\Annotation;
use App\Entity\Document;
use App\Entity\Participant;
use App\Entity\Session;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Uid\Uuid;

class AnnotationTest extends TestCase
{
    private Annotation $annotation;

    protected function setUp(): void
    {
        $this->annotation = new Annotation();
    }

    public function testConstructorGeneratesUuidV7(): void
    {
        $id = $this->annotation->getId();

        $this->assertInstanceOf(Uuid::class, $id);
        // UUIDv7 has version 7 in the 13th character position
        $this->assertMatchesRegularExpression('/^[0-9a-f]{8}-[0-9a-f]{4}-7[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i', $id->toString());
    }

    public function testDefaultTypeIsComment(): void
    {
        $this->assertSame(Annotation::TYPE_COMMENT, $this->annotation->getType());
    }

    public function testDefaultStatusIsOpen(): void
    {
        $this->assertSame(Annotation::STATUS_OPEN, $this->annotation->getStatus());
    }

    public function testDefaultTakenIntoAccountIsFalse(): void
    {
        $this->assertFalse($this->annotation->isTakenIntoAccount());
    }

    public function testRepliesCollectionIsInitialized(): void
    {
        $replies = $this->annotation->getReplies();

        $this->assertCount(0, $replies);
    }

    public function testConstructorSetsCreatedAt(): void
    {
        $createdAt = $this->annotation->getCreatedAt();

        $this->assertInstanceOf(\DateTimeImmutable::class, $createdAt);
        $this->assertEqualsWithDelta(new \DateTimeImmutable(), $createdAt, 1);
    }

    public function testConstructorSetsUpdatedAt(): void
    {
        $updatedAt = $this->annotation->getUpdatedAt();

        $this->assertInstanceOf(\DateTimeImmutable::class, $updatedAt);
        $this->assertEqualsWithDelta(new \DateTimeImmutable(), $updatedAt, 1);
    }

    public function testSetAndGetContent(): void
    {
        $content = 'This is an annotation content';

        $this->annotation->setContent($content);

        $this->assertSame($content, $this->annotation->getContent());
    }

    public function testSetAndGetType(): void
    {
        $this->annotation->setType(Annotation::TYPE_COMMENT);

        $this->assertSame(Annotation::TYPE_COMMENT, $this->annotation->getType());
    }

    public function testSetAndGetStatus(): void
    {
        $this->annotation->setStatus(Annotation::STATUS_IN_PROGRESS);

        $this->assertSame(Annotation::STATUS_IN_PROGRESS, $this->annotation->getStatus());
    }

    public function testSetAndGetDocument(): void
    {
        $session = new Session();
        $session->setTitle('Test Session');

        $document = new Document();
        $document->setSession($session);
        $document->setTitle('Test Document');
        $document->setSlug('test-document');

        $this->annotation->setDocument($document);

        $this->assertSame($document, $this->annotation->getDocument());
    }

    public function testSetAndGetAuthor(): void
    {
        $session = new Session();
        $session->setTitle('Test Session');

        $participant = new Participant();
        $participant->setSession($session);
        $participant->setPseudo('Author');

        $this->annotation->setAuthor($participant);

        $this->assertSame($participant, $this->annotation->getAuthor());
    }

    public function testAddReplyAddsToCollection(): void
    {
        $reply = new Annotation();
        $reply->setContent('Reply content');

        $this->annotation->addReply($reply);

        $this->assertCount(1, $this->annotation->getReplies());
        $this->assertTrue($this->annotation->getReplies()->contains($reply));
    }

    public function testAddReplySetsParentAnnotation(): void
    {
        $reply = new Annotation();
        $reply->setContent('Reply content');

        $this->annotation->addReply($reply);

        $this->assertSame($this->annotation, $reply->getParentAnnotation());
    }

    public function testAddReplyDoesNotAddDuplicate(): void
    {
        $reply = new Annotation();
        $reply->setContent('Reply content');

        $this->annotation->addReply($reply);
        $this->annotation->addReply($reply);

        $this->assertCount(1, $this->annotation->getReplies());
    }

    public function testRemoveReplyRemovesFromCollection(): void
    {
        $reply = new Annotation();
        $reply->setContent('Reply content');

        $this->annotation->addReply($reply);
        $this->annotation->removeReply($reply);

        $this->assertCount(0, $this->annotation->getReplies());
    }

    public function testRemoveReplyUnsetsParentAnnotation(): void
    {
        $reply = new Annotation();
        $reply->setContent('Reply content');

        $this->annotation->addReply($reply);
        $this->annotation->removeReply($reply);

        $this->assertNull($reply->getParentAnnotation());
    }

    public function testIsReplyReturnsFalseForRootAnnotation(): void
    {
        $this->assertFalse($this->annotation->isReply());
    }

    public function testIsReplyReturnsTrueForReply(): void
    {
        $parent = new Annotation();
        $parent->setContent('Parent content');

        $reply = new Annotation();
        $reply->setContent('Reply content');

        $parent->addReply($reply);

        $this->assertTrue($reply->isReply());
    }

    public function testSetAndGetMentions(): void
    {
        $mentions = ['uuid-1', 'uuid-2'];

        $this->annotation->setMentions($mentions);

        $this->assertSame($mentions, $this->annotation->getMentions());
    }

    public function testMentionsCanBeNull(): void
    {
        $this->annotation->setMentions(['uuid']);
        $this->annotation->setMentions(null);

        $this->assertNull($this->annotation->getMentions());
    }

    public function testMentionsDefaultIsNull(): void
    {
        $this->assertNull($this->annotation->getMentions());
    }

    public function testMentionsCanBeEmptyArray(): void
    {
        $this->annotation->setMentions([]);

        $this->assertSame([], $this->annotation->getMentions());
    }

    public function testMentionsCanContainMultipleIds(): void
    {
        $mentions = ['uuid-1', 'uuid-2', 'uuid-3', 'uuid-4', 'uuid-5'];

        $this->annotation->setMentions($mentions);

        $this->assertSame($mentions, $this->annotation->getMentions());
        $this->assertCount(5, $this->annotation->getMentions());
    }

    public function testResolveSetsStatusToResolved(): void
    {
        $session = new Session();
        $session->setTitle('Test Session');

        $resolver = new Participant();
        $resolver->setSession($session);
        $resolver->setPseudo('Resolver');

        $this->annotation->resolve($resolver);

        $this->assertSame(Annotation::STATUS_RESOLVED, $this->annotation->getStatus());
    }

    public function testResolveSetsResolvedBy(): void
    {
        $session = new Session();
        $session->setTitle('Test Session');

        $resolver = new Participant();
        $resolver->setSession($session);
        $resolver->setPseudo('Resolver');

        $this->annotation->resolve($resolver);

        $this->assertSame($resolver, $this->annotation->getResolvedBy());
    }

    public function testResolveReturnsSelf(): void
    {
        $resolver = new Participant();

        $result = $this->annotation->resolve($resolver);

        $this->assertSame($this->annotation, $result);
    }

    public function testSetAndGetAnchor(): void
    {
        $anchor = ['start' => 10, 'end' => 50, 'text' => 'selected text'];

        $this->annotation->setAnchor($anchor);

        $this->assertSame($anchor, $this->annotation->getAnchor());
    }

    public function testAnchorCanBeNull(): void
    {
        $this->annotation->setAnchor(['start' => 0]);
        $this->annotation->setAnchor(null);

        $this->assertNull($this->annotation->getAnchor());
    }

    public function testAnchorDefaultIsNull(): void
    {
        $this->assertNull($this->annotation->getAnchor());
    }

    public function testAnchorCanBeComplexObject(): void
    {
        $anchor = [
            'type' => 'text-selection',
            'range' => [
                'start' => ['paragraph' => 2, 'offset' => 15],
                'end' => ['paragraph' => 2, 'offset' => 45],
            ],
            'selectedText' => 'This is the selected text',
            'context' => [
                'before' => 'Some text before. ',
                'after' => ' Some text after.',
            ],
        ];

        $this->annotation->setAnchor($anchor);

        $this->assertSame($anchor, $this->annotation->getAnchor());
    }

    public function testSetTakenIntoAccount(): void
    {
        $this->annotation->setTakenIntoAccount(true);

        $this->assertTrue($this->annotation->isTakenIntoAccount());
    }

    public function testSetTakenIntoAccountToFalse(): void
    {
        $this->annotation->setTakenIntoAccount(true);
        $this->annotation->setTakenIntoAccount(false);

        $this->assertFalse($this->annotation->isTakenIntoAccount());
    }

    public function testGetAndSetResolvedBy(): void
    {
        $resolver = new Participant();

        $this->annotation->setResolvedBy($resolver);

        $this->assertSame($resolver, $this->annotation->getResolvedBy());
    }

    public function testResolvedByCanBeNull(): void
    {
        $resolver = new Participant();
        $this->annotation->setResolvedBy($resolver);
        $this->annotation->setResolvedBy(null);

        $this->assertNull($this->annotation->getResolvedBy());
    }

    public function testResolvedByDefaultIsNull(): void
    {
        $this->assertNull($this->annotation->getResolvedBy());
    }

    public function testReplyToReply(): void
    {
        $root = new Annotation();
        $root->setContent('Root annotation');

        $reply1 = new Annotation();
        $reply1->setContent('First level reply');
        $root->addReply($reply1);

        // Even if the model allows it, verify the structure
        $this->assertTrue($reply1->isReply());
        $this->assertSame($root, $reply1->getParentAnnotation());
    }

    public function testContentCanBeMultiline(): void
    {
        $content = "Line 1\nLine 2\nLine 3\n\nParagraph 2";

        $this->annotation->setContent($content);

        $this->assertSame($content, $this->annotation->getContent());
    }

    public function testStatusConstants(): void
    {
        $this->assertSame('open', Annotation::STATUS_OPEN);
        $this->assertSame('in_progress', Annotation::STATUS_IN_PROGRESS);
        $this->assertSame('resolved', Annotation::STATUS_RESOLVED);
    }

    public function testTypeConstants(): void
    {
        $this->assertSame('comment', Annotation::TYPE_COMMENT);
    }

    public function testStatusesArray(): void
    {
        $this->assertContains(Annotation::STATUS_OPEN, Annotation::STATUSES);
        $this->assertContains(Annotation::STATUS_IN_PROGRESS, Annotation::STATUSES);
        $this->assertContains(Annotation::STATUS_RESOLVED, Annotation::STATUSES);
        $this->assertCount(3, Annotation::STATUSES);
    }

    public function testTypesArray(): void
    {
        $this->assertContains(Annotation::TYPE_COMMENT, Annotation::TYPES);
        $this->assertCount(1, Annotation::TYPES);
    }

    public function testSetAndGetParentAnnotation(): void
    {
        $parent = new Annotation();

        $this->annotation->setParentAnnotation($parent);

        $this->assertSame($parent, $this->annotation->getParentAnnotation());
    }

    public function testSetParentAnnotationToNull(): void
    {
        $parent = new Annotation();
        $this->annotation->setParentAnnotation($parent);
        $this->annotation->setParentAnnotation(null);

        $this->assertNull($this->annotation->getParentAnnotation());
        $this->assertFalse($this->annotation->isReply());
    }

    public function testSetContentReturnsSelf(): void
    {
        $result = $this->annotation->setContent('test');

        $this->assertSame($this->annotation, $result);
    }

    public function testSetTypeReturnsSelf(): void
    {
        $result = $this->annotation->setType(Annotation::TYPE_COMMENT);

        $this->assertSame($this->annotation, $result);
    }

    public function testSetStatusReturnsSelf(): void
    {
        $result = $this->annotation->setStatus(Annotation::STATUS_OPEN);

        $this->assertSame($this->annotation, $result);
    }

    public function testSetDocumentReturnsSelf(): void
    {
        $document = new Document();

        $result = $this->annotation->setDocument($document);

        $this->assertSame($this->annotation, $result);
    }

    public function testSetAuthorReturnsSelf(): void
    {
        $author = new Participant();

        $result = $this->annotation->setAuthor($author);

        $this->assertSame($this->annotation, $result);
    }

    public function testAddReplyReturnsSelf(): void
    {
        $reply = new Annotation();

        $result = $this->annotation->addReply($reply);

        $this->assertSame($this->annotation, $result);
    }

    public function testRemoveReplyReturnsSelf(): void
    {
        $reply = new Annotation();
        $this->annotation->addReply($reply);

        $result = $this->annotation->removeReply($reply);

        $this->assertSame($this->annotation, $result);
    }

    public function testMultipleReplies(): void
    {
        $reply1 = new Annotation();
        $reply1->setContent('Reply 1');

        $reply2 = new Annotation();
        $reply2->setContent('Reply 2');

        $reply3 = new Annotation();
        $reply3->setContent('Reply 3');

        $this->annotation->addReply($reply1);
        $this->annotation->addReply($reply2);
        $this->annotation->addReply($reply3);

        $this->assertCount(3, $this->annotation->getReplies());
    }

    public function testMultipleAnnotationsHaveUniqueIds(): void
    {
        $annotation1 = new Annotation();
        $annotation2 = new Annotation();

        $this->assertFalse($annotation1->getId()->equals($annotation2->getId()));
    }
}

<?php

namespace App\Tests\Unit\Entity;

use App\Entity\Decision;
use App\Entity\Participant;
use App\Entity\Session;
use App\Entity\Vote;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Uid\Uuid;

class VoteTest extends TestCase
{
    private Vote $vote;

    protected function setUp(): void
    {
        $this->vote = new Vote();
    }

    public function testConstructorGeneratesUuidV7(): void
    {
        $id = $this->vote->getId();

        $this->assertInstanceOf(Uuid::class, $id);
        // UUIDv7 starts with a timestamp-based prefix
        $this->assertMatchesRegularExpression('/^[0-9a-f]{8}-[0-9a-f]{4}-7[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i', $id->toString());
    }

    public function testConstructorSetsCreatedAt(): void
    {
        $createdAt = $this->vote->getCreatedAt();

        $this->assertInstanceOf(\DateTimeImmutable::class, $createdAt);
        $this->assertEqualsWithDelta(new \DateTimeImmutable(), $createdAt, 1);
    }

    public function testSetAndGetDecision(): void
    {
        $session = new Session();
        $session->setTitle('Test Session');

        $decision = new Decision();
        $decision->setSession($session);
        $decision->setTitle('Test Decision');

        $this->vote->setDecision($decision);

        $this->assertSame($decision, $this->vote->getDecision());
    }

    public function testSetAndGetParticipant(): void
    {
        $session = new Session();
        $session->setTitle('Test Session');

        $participant = new Participant();
        $participant->setSession($session);
        $participant->setPseudo('TestUser');

        $this->vote->setParticipant($participant);

        $this->assertSame($participant, $this->vote->getParticipant());
    }

    public function testSetAndGetOptionId(): void
    {
        $optionId = Uuid::v7();

        $this->vote->setOptionId($optionId);

        $this->assertSame($optionId, $this->vote->getOptionId());
    }

    public function testSetAndGetComment(): void
    {
        $comment = 'This is my vote comment';

        $this->vote->setComment($comment);

        $this->assertSame($comment, $this->vote->getComment());
    }

    public function testCommentCanBeNull(): void
    {
        $this->vote->setComment('Some comment');
        $this->vote->setComment(null);

        $this->assertNull($this->vote->getComment());
    }

    public function testCommentDefaultIsNull(): void
    {
        $this->assertNull($this->vote->getComment());
    }

    public function testCommentCanBeLongText(): void
    {
        $longComment = str_repeat('This is a long comment. ', 1000);

        $this->vote->setComment($longComment);

        $this->assertSame($longComment, $this->vote->getComment());
    }

    public function testSetDecisionReturnsSelf(): void
    {
        $decision = new Decision();

        $result = $this->vote->setDecision($decision);

        $this->assertSame($this->vote, $result);
    }

    public function testSetParticipantReturnsSelf(): void
    {
        $participant = new Participant();

        $result = $this->vote->setParticipant($participant);

        $this->assertSame($this->vote, $result);
    }

    public function testSetOptionIdReturnsSelf(): void
    {
        $optionId = Uuid::v7();

        $result = $this->vote->setOptionId($optionId);

        $this->assertSame($this->vote, $result);
    }

    public function testSetCommentReturnsSelf(): void
    {
        $result = $this->vote->setComment('Test');

        $this->assertSame($this->vote, $result);
    }

    public function testMultipleVotesHaveUniqueIds(): void
    {
        $vote1 = new Vote();
        $vote2 = new Vote();
        $vote3 = new Vote();

        $this->assertFalse($vote1->getId()->equals($vote2->getId()));
        $this->assertFalse($vote2->getId()->equals($vote3->getId()));
        $this->assertFalse($vote1->getId()->equals($vote3->getId()));
    }

    public function testCommentCanContainSpecialCharacters(): void
    {
        $comment = "Test avec accents: éàü\nNouvelle ligne\tTab et emoji 🗳️";

        $this->vote->setComment($comment);

        $this->assertSame($comment, $this->vote->getComment());
    }
}

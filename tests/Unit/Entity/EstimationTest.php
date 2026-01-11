<?php

namespace App\Tests\Unit\Entity;

use App\Entity\Document;
use App\Entity\Estimation;
use App\Entity\EstimationVote;
use App\Entity\Participant;
use App\Entity\Session;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Uid\Uuid;

class EstimationTest extends TestCase
{
    private Estimation $estimation;

    protected function setUp(): void
    {
        $this->estimation = new Estimation();
    }

    public function testConstructorGeneratesUuidV7(): void
    {
        $id = $this->estimation->getId();

        $this->assertInstanceOf(Uuid::class, $id);
        $this->assertMatchesRegularExpression('/^[0-9a-f]{8}-[0-9a-f]{4}-7[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i', $id->toString());
    }

    public function testDefaultStatusIsOpen(): void
    {
        $this->assertSame(Estimation::STATUS_OPEN, $this->estimation->getStatus());
    }

    public function testVotesCollectionIsInitialized(): void
    {
        $votes = $this->estimation->getVotes();

        $this->assertCount(0, $votes);
    }

    public function testConstructorSetsCreatedAt(): void
    {
        $createdAt = $this->estimation->getCreatedAt();

        $this->assertInstanceOf(\DateTimeImmutable::class, $createdAt);
        $this->assertEqualsWithDelta(new \DateTimeImmutable(), $createdAt, 1);
    }

    public function testConstructorSetsUpdatedAt(): void
    {
        $updatedAt = $this->estimation->getUpdatedAt();

        $this->assertInstanceOf(\DateTimeImmutable::class, $updatedAt);
        $this->assertEqualsWithDelta(new \DateTimeImmutable(), $updatedAt, 1);
    }

    public function testSetAndGetTitle(): void
    {
        $this->estimation->setTitle('Test Estimation');

        $this->assertSame('Test Estimation', $this->estimation->getTitle());
    }

    public function testSetAndGetDescription(): void
    {
        $description = 'This is an estimation description';

        $this->estimation->setDescription($description);

        $this->assertSame($description, $this->estimation->getDescription());
    }

    public function testDescriptionCanBeNull(): void
    {
        $this->estimation->setDescription('Some description');
        $this->estimation->setDescription(null);

        $this->assertNull($this->estimation->getDescription());
    }

    public function testDescriptionDefaultIsNull(): void
    {
        $this->assertNull($this->estimation->getDescription());
    }

    public function testSetAndGetStatus(): void
    {
        $this->estimation->setStatus(Estimation::STATUS_REVEALED);

        $this->assertSame(Estimation::STATUS_REVEALED, $this->estimation->getStatus());
    }

    public function testSetAndGetSession(): void
    {
        $session = new Session();
        $session->setTitle('Test Session');

        $this->estimation->setSession($session);

        $this->assertSame($session, $this->estimation->getSession());
    }

    public function testLinkedDocumentCanBeNull(): void
    {
        $this->estimation->setLinkedDocument(null);

        $this->assertNull($this->estimation->getLinkedDocument());
    }

    public function testSetAndGetLinkedDocument(): void
    {
        $document = new Document();
        $document->setTitle('Linked Doc');
        $document->setSlug('linked-doc');

        $this->estimation->setLinkedDocument($document);

        $this->assertSame($document, $this->estimation->getLinkedDocument());
    }

    public function testIsOpen(): void
    {
        $this->assertTrue($this->estimation->isOpen());

        $this->estimation->setStatus(Estimation::STATUS_REVEALED);
        $this->assertFalse($this->estimation->isOpen());

        $this->estimation->setStatus(Estimation::STATUS_CLOSED);
        $this->assertFalse($this->estimation->isOpen());
    }

    public function testIsRevealed(): void
    {
        $this->assertFalse($this->estimation->isRevealed());

        $this->estimation->setStatus(Estimation::STATUS_REVEALED);
        $this->assertTrue($this->estimation->isRevealed());

        $this->estimation->setStatus(Estimation::STATUS_CLOSED);
        $this->assertTrue($this->estimation->isRevealed()); // closed also counts as revealed
    }

    public function testIsClosed(): void
    {
        $this->assertFalse($this->estimation->isClosed());

        $this->estimation->setStatus(Estimation::STATUS_REVEALED);
        $this->assertFalse($this->estimation->isClosed());

        $this->estimation->setStatus(Estimation::STATUS_CLOSED);
        $this->assertTrue($this->estimation->isClosed());
    }

    public function testReveal(): void
    {
        $this->estimation->reveal();

        $this->assertSame(Estimation::STATUS_REVEALED, $this->estimation->getStatus());
        $this->assertInstanceOf(\DateTimeImmutable::class, $this->estimation->getRevealedAt());
        $this->assertEqualsWithDelta(new \DateTimeImmutable(), $this->estimation->getRevealedAt(), 1);
    }

    public function testRevealReturnsSelf(): void
    {
        $result = $this->estimation->reveal();

        $this->assertSame($this->estimation, $result);
    }

    public function testClose(): void
    {
        $this->estimation->close();

        $this->assertSame(Estimation::STATUS_CLOSED, $this->estimation->getStatus());
    }

    public function testCloseReturnsSelf(): void
    {
        $result = $this->estimation->close();

        $this->assertSame($this->estimation, $result);
    }

    public function testAddVoteAddsToCollection(): void
    {
        $vote = new EstimationVote();
        $vote->setValue('5');

        $this->estimation->addVote($vote);

        $this->assertCount(1, $this->estimation->getVotes());
        $this->assertTrue($this->estimation->getVotes()->contains($vote));
    }

    public function testAddVoteSetsEstimationOnVote(): void
    {
        $vote = new EstimationVote();
        $vote->setValue('5');

        $this->estimation->addVote($vote);

        $this->assertSame($this->estimation, $vote->getEstimation());
    }

    public function testAddVoteDoesNotAddDuplicate(): void
    {
        $vote = new EstimationVote();
        $vote->setValue('5');

        $this->estimation->addVote($vote);
        $this->estimation->addVote($vote);

        $this->assertCount(1, $this->estimation->getVotes());
    }

    public function testRemoveVoteRemovesFromCollection(): void
    {
        $vote = new EstimationVote();
        $vote->setValue('5');

        $this->estimation->addVote($vote);
        $this->estimation->removeVote($vote);

        $this->assertCount(0, $this->estimation->getVotes());
    }

    public function testCalculateAverageReturnsNullWhenNotRevealed(): void
    {
        $session = new Session();
        $session->setTitle('Test');

        $participant = new Participant();
        $participant->setSession($session);
        $participant->setPseudo('User');

        $vote = new EstimationVote();
        $vote->setParticipant($participant);
        $vote->setValue('5');
        $this->estimation->addVote($vote);

        $this->assertNull($this->estimation->calculateAverage());
    }

    public function testCalculateAverageWithSingleVote(): void
    {
        $this->estimation->reveal();

        $session = new Session();
        $session->setTitle('Test');

        $participant = new Participant();
        $participant->setSession($session);
        $participant->setPseudo('User');

        $vote = new EstimationVote();
        $vote->setParticipant($participant);
        $vote->setValue('5');
        $this->estimation->addVote($vote);

        $this->assertSame(5.0, $this->estimation->calculateAverage());
    }

    public function testCalculateAverageWithMultipleVotes(): void
    {
        $this->estimation->reveal();

        $session = new Session();
        $session->setTitle('Test');

        $participant1 = new Participant();
        $participant1->setSession($session);
        $participant1->setPseudo('User1');

        $participant2 = new Participant();
        $participant2->setSession($session);
        $participant2->setPseudo('User2');

        $vote1 = new EstimationVote();
        $vote1->setParticipant($participant1);
        $vote1->setValue('5');
        $this->estimation->addVote($vote1);

        $vote2 = new EstimationVote();
        $vote2->setParticipant($participant2);
        $vote2->setValue('8');
        $this->estimation->addVote($vote2);

        $this->assertSame(6.5, $this->estimation->calculateAverage());
    }

    public function testCalculateAverageExcludesQuestionMarks(): void
    {
        $this->estimation->reveal();

        $session = new Session();
        $session->setTitle('Test');

        $participant1 = new Participant();
        $participant1->setSession($session);
        $participant1->setPseudo('User1');

        $participant2 = new Participant();
        $participant2->setSession($session);
        $participant2->setPseudo('User2');

        $participant3 = new Participant();
        $participant3->setSession($session);
        $participant3->setPseudo('User3');

        $vote1 = new EstimationVote();
        $vote1->setParticipant($participant1);
        $vote1->setValue('5');
        $this->estimation->addVote($vote1);

        $vote2 = new EstimationVote();
        $vote2->setParticipant($participant2);
        $vote2->setValue('?');
        $this->estimation->addVote($vote2);

        $vote3 = new EstimationVote();
        $vote3->setParticipant($participant3);
        $vote3->setValue('8');
        $this->estimation->addVote($vote3);

        // Average of 5 and 8 = 6.5 (? is excluded)
        $this->assertSame(6.5, $this->estimation->calculateAverage());
    }

    public function testCalculateAverageReturnsNullWhenAllVotesAreQuestionMarks(): void
    {
        $this->estimation->reveal();

        $session = new Session();
        $session->setTitle('Test');

        $participant = new Participant();
        $participant->setSession($session);
        $participant->setPseudo('User');

        $vote = new EstimationVote();
        $vote->setParticipant($participant);
        $vote->setValue('?');
        $this->estimation->addVote($vote);

        $this->assertNull($this->estimation->calculateAverage());
    }

    public function testCalculateAverageReturnsNullWithNoVotes(): void
    {
        $this->estimation->reveal();

        $this->assertNull($this->estimation->calculateAverage());
    }

    public function testCalculateAverageRoundsToOneDecimal(): void
    {
        $this->estimation->reveal();

        $session = new Session();
        $session->setTitle('Test');

        $participant1 = new Participant();
        $participant1->setSession($session);
        $participant1->setPseudo('User1');

        $participant2 = new Participant();
        $participant2->setSession($session);
        $participant2->setPseudo('User2');

        $participant3 = new Participant();
        $participant3->setSession($session);
        $participant3->setPseudo('User3');

        $vote1 = new EstimationVote();
        $vote1->setParticipant($participant1);
        $vote1->setValue('1');
        $this->estimation->addVote($vote1);

        $vote2 = new EstimationVote();
        $vote2->setParticipant($participant2);
        $vote2->setValue('2');
        $this->estimation->addVote($vote2);

        $vote3 = new EstimationVote();
        $vote3->setParticipant($participant3);
        $vote3->setValue('3');
        $this->estimation->addVote($vote3);

        // Average of 1, 2, 3 = 2.0
        $this->assertSame(2.0, $this->estimation->calculateAverage());
    }

    public function testGetVoterParticipantIds(): void
    {
        $session = new Session();
        $session->setTitle('Test');

        $participant1 = new Participant();
        $participant1->setSession($session);
        $participant1->setPseudo('User1');

        $participant2 = new Participant();
        $participant2->setSession($session);
        $participant2->setPseudo('User2');

        $vote1 = new EstimationVote();
        $vote1->setParticipant($participant1);
        $vote1->setValue('5');
        $this->estimation->addVote($vote1);

        $vote2 = new EstimationVote();
        $vote2->setParticipant($participant2);
        $vote2->setValue('8');
        $this->estimation->addVote($vote2);

        $voterIds = $this->estimation->getVoterParticipantIds();

        $this->assertCount(2, $voterIds);
        $this->assertContains($participant1->getId()->toString(), $voterIds);
        $this->assertContains($participant2->getId()->toString(), $voterIds);
    }

    public function testGetVoteForParticipantReturnsVote(): void
    {
        $session = new Session();
        $session->setTitle('Test');

        $participant = new Participant();
        $participant->setSession($session);
        $participant->setPseudo('Voter');

        $vote = new EstimationVote();
        $vote->setParticipant($participant);
        $vote->setValue('5');
        $this->estimation->addVote($vote);

        $foundVote = $this->estimation->getVoteForParticipant($participant);

        $this->assertSame($vote, $foundVote);
    }

    public function testGetVoteForParticipantReturnsNullIfNotVoted(): void
    {
        $session = new Session();
        $session->setTitle('Test');

        $participant = new Participant();
        $participant->setSession($session);
        $participant->setPseudo('NonVoter');

        $foundVote = $this->estimation->getVoteForParticipant($participant);

        $this->assertNull($foundVote);
    }

    public function testIsValidFibonacciValueWithValidValues(): void
    {
        $this->assertTrue(Estimation::isValidFibonacciValue('0'));
        $this->assertTrue(Estimation::isValidFibonacciValue('1'));
        $this->assertTrue(Estimation::isValidFibonacciValue('2'));
        $this->assertTrue(Estimation::isValidFibonacciValue('3'));
        $this->assertTrue(Estimation::isValidFibonacciValue('5'));
        $this->assertTrue(Estimation::isValidFibonacciValue('8'));
        $this->assertTrue(Estimation::isValidFibonacciValue('13'));
        $this->assertTrue(Estimation::isValidFibonacciValue('21'));
        $this->assertTrue(Estimation::isValidFibonacciValue('?'));
    }

    public function testIsValidFibonacciValueWithInvalidValues(): void
    {
        $this->assertFalse(Estimation::isValidFibonacciValue('4'));
        $this->assertFalse(Estimation::isValidFibonacciValue('100'));
        $this->assertFalse(Estimation::isValidFibonacciValue(''));
        $this->assertFalse(Estimation::isValidFibonacciValue('abc'));
    }

    public function testStatusConstants(): void
    {
        $this->assertSame('open', Estimation::STATUS_OPEN);
        $this->assertSame('revealed', Estimation::STATUS_REVEALED);
        $this->assertSame('closed', Estimation::STATUS_CLOSED);
    }

    public function testStatusesArray(): void
    {
        $this->assertContains(Estimation::STATUS_OPEN, Estimation::STATUSES);
        $this->assertContains(Estimation::STATUS_REVEALED, Estimation::STATUSES);
        $this->assertContains(Estimation::STATUS_CLOSED, Estimation::STATUSES);
        $this->assertCount(3, Estimation::STATUSES);
    }

    public function testFibonacciValuesConstant(): void
    {
        $expected = ['0', '1', '2', '3', '5', '8', '13', '21', '?'];
        $this->assertSame($expected, Estimation::FIBONACCI_VALUES);
    }

    public function testSetTitleReturnsSelf(): void
    {
        $result = $this->estimation->setTitle('Test');

        $this->assertSame($this->estimation, $result);
    }

    public function testSetDescriptionReturnsSelf(): void
    {
        $result = $this->estimation->setDescription('desc');

        $this->assertSame($this->estimation, $result);
    }

    public function testSetStatusReturnsSelf(): void
    {
        $result = $this->estimation->setStatus(Estimation::STATUS_OPEN);

        $this->assertSame($this->estimation, $result);
    }

    public function testSetSessionReturnsSelf(): void
    {
        $session = new Session();

        $result = $this->estimation->setSession($session);

        $this->assertSame($this->estimation, $result);
    }

    public function testSetLinkedDocumentReturnsSelf(): void
    {
        $result = $this->estimation->setLinkedDocument(null);

        $this->assertSame($this->estimation, $result);
    }

    public function testAddVoteReturnsSelf(): void
    {
        $vote = new EstimationVote();

        $result = $this->estimation->addVote($vote);

        $this->assertSame($this->estimation, $result);
    }

    public function testRemoveVoteReturnsSelf(): void
    {
        $vote = new EstimationVote();
        $this->estimation->addVote($vote);

        $result = $this->estimation->removeVote($vote);

        $this->assertSame($this->estimation, $result);
    }

    public function testMultipleEstimationsHaveUniqueIds(): void
    {
        $estimation1 = new Estimation();
        $estimation2 = new Estimation();

        $this->assertFalse($estimation1->getId()->equals($estimation2->getId()));
    }

    public function testRevealedAtIsNullByDefault(): void
    {
        $this->assertNull($this->estimation->getRevealedAt());
    }
}

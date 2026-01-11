<?php

namespace App\Tests\Unit\Entity;

use App\Entity\Decision;
use App\Entity\Document;
use App\Entity\Participant;
use App\Entity\Session;
use App\Entity\Vote;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Uid\Uuid;

class DecisionTest extends TestCase
{
    private Decision $decision;

    protected function setUp(): void
    {
        $this->decision = new Decision();
    }

    public function testConstructorGeneratesUuidV7(): void
    {
        $id = $this->decision->getId();

        $this->assertInstanceOf(Uuid::class, $id);
        // UUIDv7 has version 7 in the 13th character position
        $this->assertMatchesRegularExpression('/^[0-9a-f]{8}-[0-9a-f]{4}-7[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i', $id->toString());
    }

    public function testDefaultStatusIsOuvert(): void
    {
        $this->assertSame(Decision::STATUS_OUVERT, $this->decision->getStatus());
    }

    public function testDefaultOptionsIsEmptyArray(): void
    {
        $this->assertSame([], $this->decision->getOptions());
    }

    public function testDefaultIsLockedIsFalse(): void
    {
        $this->assertFalse($this->decision->isLocked());
    }

    public function testVotesCollectionIsInitialized(): void
    {
        $votes = $this->decision->getVotes();

        $this->assertCount(0, $votes);
    }

    public function testConstructorSetsCreatedAt(): void
    {
        $createdAt = $this->decision->getCreatedAt();

        $this->assertInstanceOf(\DateTimeImmutable::class, $createdAt);
        $this->assertEqualsWithDelta(new \DateTimeImmutable(), $createdAt, 1);
    }

    public function testConstructorSetsUpdatedAt(): void
    {
        $updatedAt = $this->decision->getUpdatedAt();

        $this->assertInstanceOf(\DateTimeImmutable::class, $updatedAt);
        $this->assertEqualsWithDelta(new \DateTimeImmutable(), $updatedAt, 1);
    }

    public function testSetAndGetTitle(): void
    {
        $this->decision->setTitle('Test Decision');

        $this->assertSame('Test Decision', $this->decision->getTitle());
    }

    public function testSetAndGetDescription(): void
    {
        $description = 'This is a decision description';

        $this->decision->setDescription($description);

        $this->assertSame($description, $this->decision->getDescription());
    }

    public function testDescriptionCanBeNull(): void
    {
        $this->decision->setDescription('Some description');
        $this->decision->setDescription(null);

        $this->assertNull($this->decision->getDescription());
    }

    public function testDescriptionDefaultIsNull(): void
    {
        $this->assertNull($this->decision->getDescription());
    }

    public function testSetAndGetStatus(): void
    {
        $this->decision->setStatus(Decision::STATUS_EN_DISCUSSION);

        $this->assertSame(Decision::STATUS_EN_DISCUSSION, $this->decision->getStatus());
    }

    public function testSetAndGetSession(): void
    {
        $session = new Session();
        $session->setTitle('Test Session');

        $this->decision->setSession($session);

        $this->assertSame($session, $this->decision->getSession());
    }

    public function testAddOptionCreatesNewOption(): void
    {
        $this->decision->addOption('Option A');

        $options = $this->decision->getOptions();

        $this->assertCount(1, $options);
        $this->assertSame('Option A', $options[0]['label']);
    }

    public function testAddOptionGeneratesUuidForOption(): void
    {
        $this->decision->addOption('Option A');

        $options = $this->decision->getOptions();

        $this->assertArrayHasKey('id', $options[0]);
        $this->assertTrue(Uuid::isValid($options[0]['id']));
    }

    public function testAddOptionWithDescription(): void
    {
        $this->decision->addOption('Option A', 'Description for A');

        $options = $this->decision->getOptions();

        $this->assertSame('Description for A', $options[0]['description']);
    }

    public function testAddOptionWithoutDescription(): void
    {
        $this->decision->addOption('Option A');

        $options = $this->decision->getOptions();

        $this->assertNull($options[0]['description']);
    }

    public function testSetOptionsReplacesAll(): void
    {
        $this->decision->addOption('Original Option');

        $newOptions = [
            ['id' => Uuid::v7()->toString(), 'label' => 'New Option 1', 'description' => null],
            ['id' => Uuid::v7()->toString(), 'label' => 'New Option 2', 'description' => null],
        ];

        $this->decision->setOptions($newOptions);

        $this->assertCount(2, $this->decision->getOptions());
        $this->assertSame('New Option 1', $this->decision->getOptions()[0]['label']);
    }

    public function testLockSetsIsLockedToTrue(): void
    {
        $this->decision->lock();

        $this->assertTrue($this->decision->isLocked());
    }

    public function testLockReturnsSelf(): void
    {
        $result = $this->decision->lock();

        $this->assertSame($this->decision, $result);
    }

    public function testValidateSetsSelectedOptionId(): void
    {
        $optionId = Uuid::v7();

        $this->decision->validate($optionId);

        $this->assertTrue($optionId->equals($this->decision->getSelectedOptionId()));
    }

    public function testValidateSetsStatusToValide(): void
    {
        $optionId = Uuid::v7();

        $this->decision->validate($optionId);

        $this->assertSame(Decision::STATUS_VALIDE, $this->decision->getStatus());
    }

    public function testValidateSetsIsLockedToTrue(): void
    {
        $optionId = Uuid::v7();

        $this->decision->validate($optionId);

        $this->assertTrue($this->decision->isLocked());
    }

    public function testValidateReturnsSelf(): void
    {
        $optionId = Uuid::v7();

        $result = $this->decision->validate($optionId);

        $this->assertSame($this->decision, $result);
    }

    public function testGetVoteStatsWithNoVotes(): void
    {
        $this->decision->addOption('Option A');
        $this->decision->addOption('Option B');

        $stats = $this->decision->getVoteStats();

        $this->assertCount(2, $stats);
        foreach ($stats as $count) {
            $this->assertSame(0, $count);
        }
    }

    public function testGetVoteStatsWithVotes(): void
    {
        $this->decision->addOption('Option A');
        $this->decision->addOption('Option B');

        $options = $this->decision->getOptions();
        $optionAId = Uuid::fromString($options[0]['id']);
        $optionBId = Uuid::fromString($options[1]['id']);

        $session = new Session();
        $session->setTitle('Test');

        $participant1 = new Participant();
        $participant1->setSession($session);
        $participant1->setPseudo('User1');

        $participant2 = new Participant();
        $participant2->setSession($session);
        $participant2->setPseudo('User2');

        $vote1 = new Vote();
        $vote1->setParticipant($participant1);
        $vote1->setOptionId($optionAId);
        $this->decision->addVote($vote1);

        $vote2 = new Vote();
        $vote2->setParticipant($participant2);
        $vote2->setOptionId($optionAId);
        $this->decision->addVote($vote2);

        $stats = $this->decision->getVoteStats();

        $this->assertSame(2, $stats[$options[0]['id']]);
        $this->assertSame(0, $stats[$options[1]['id']]);
    }

    public function testGetVoteStatsInitializesAllOptions(): void
    {
        $this->decision->addOption('A');
        $this->decision->addOption('B');
        $this->decision->addOption('C');

        $stats = $this->decision->getVoteStats();
        $options = $this->decision->getOptions();

        foreach ($options as $option) {
            $this->assertArrayHasKey($option['id'], $stats);
        }
    }

    public function testGetVoteStatsIgnoresInvalidOptionIds(): void
    {
        $this->decision->addOption('Option A');

        $session = new Session();
        $session->setTitle('Test');

        $participant = new Participant();
        $participant->setSession($session);
        $participant->setPseudo('User');

        // Vote with an ID that doesn't exist in options
        $invalidOptionId = Uuid::v7();
        $vote = new Vote();
        $vote->setParticipant($participant);
        $vote->setOptionId($invalidOptionId);
        $this->decision->addVote($vote);

        $stats = $this->decision->getVoteStats();

        // The invalid option ID should not be in stats
        $this->assertArrayNotHasKey($invalidOptionId->toString(), $stats);

        // Valid option should have 0 votes
        $options = $this->decision->getOptions();
        $this->assertSame(0, $stats[$options[0]['id']]);
    }

    public function testGetVoteForParticipantReturnsVote(): void
    {
        $session = new Session();
        $session->setTitle('Test');

        $participant = new Participant();
        $participant->setSession($session);
        $participant->setPseudo('Voter');

        $vote = new Vote();
        $vote->setParticipant($participant);
        $vote->setOptionId(Uuid::v7());
        $this->decision->addVote($vote);

        $foundVote = $this->decision->getVoteForParticipant($participant);

        $this->assertSame($vote, $foundVote);
    }

    public function testGetVoteForParticipantReturnsNullIfNotVoted(): void
    {
        $session = new Session();
        $session->setTitle('Test');

        $participant = new Participant();
        $participant->setSession($session);
        $participant->setPseudo('NonVoter');

        $foundVote = $this->decision->getVoteForParticipant($participant);

        $this->assertNull($foundVote);
    }

    public function testAddVoteAddsToCollection(): void
    {
        $vote = new Vote();
        $vote->setOptionId(Uuid::v7());

        $this->decision->addVote($vote);

        $this->assertCount(1, $this->decision->getVotes());
        $this->assertTrue($this->decision->getVotes()->contains($vote));
    }

    public function testAddVoteSetsDecisionOnVote(): void
    {
        $vote = new Vote();
        $vote->setOptionId(Uuid::v7());

        $this->decision->addVote($vote);

        $this->assertSame($this->decision, $vote->getDecision());
    }

    public function testAddVoteDoesNotAddDuplicate(): void
    {
        $vote = new Vote();
        $vote->setOptionId(Uuid::v7());

        $this->decision->addVote($vote);
        $this->decision->addVote($vote);

        $this->assertCount(1, $this->decision->getVotes());
    }

    public function testRemoveVoteRemovesFromCollection(): void
    {
        $vote = new Vote();
        $vote->setOptionId(Uuid::v7());

        $this->decision->addVote($vote);
        $this->decision->removeVote($vote);

        $this->assertCount(0, $this->decision->getVotes());
    }

    public function testLinkedDocumentCanBeNull(): void
    {
        $this->decision->setLinkedDocument(null);

        $this->assertNull($this->decision->getLinkedDocument());
    }

    public function testSetAndGetLinkedDocument(): void
    {
        $document = new Document();
        $document->setTitle('Linked Doc');
        $document->setSlug('linked-doc');

        $this->decision->setLinkedDocument($document);

        $this->assertSame($document, $this->decision->getLinkedDocument());
    }

    public function testSelectedOptionIdCanBeNull(): void
    {
        $this->decision->setSelectedOptionId(null);

        $this->assertNull($this->decision->getSelectedOptionId());
    }

    public function testSetAndGetSelectedOptionId(): void
    {
        $optionId = Uuid::v7();

        $this->decision->setSelectedOptionId($optionId);

        $this->assertTrue($optionId->equals($this->decision->getSelectedOptionId()));
    }

    public function testStatusConstants(): void
    {
        $this->assertSame('ouvert', Decision::STATUS_OUVERT);
        $this->assertSame('en_discussion', Decision::STATUS_EN_DISCUSSION);
        $this->assertSame('consensus', Decision::STATUS_CONSENSUS);
        $this->assertSame('valide', Decision::STATUS_VALIDE);
        $this->assertSame('reporte', Decision::STATUS_REPORTE);
    }

    public function testStatusesArray(): void
    {
        $this->assertContains(Decision::STATUS_OUVERT, Decision::STATUSES);
        $this->assertContains(Decision::STATUS_EN_DISCUSSION, Decision::STATUSES);
        $this->assertContains(Decision::STATUS_CONSENSUS, Decision::STATUSES);
        $this->assertContains(Decision::STATUS_VALIDE, Decision::STATUSES);
        $this->assertContains(Decision::STATUS_REPORTE, Decision::STATUSES);
        $this->assertCount(5, Decision::STATUSES);
    }

    public function testSetIsLocked(): void
    {
        $this->decision->setIsLocked(true);

        $this->assertTrue($this->decision->isLocked());
    }

    public function testSetIsLockedToFalse(): void
    {
        $this->decision->setIsLocked(true);
        $this->decision->setIsLocked(false);

        $this->assertFalse($this->decision->isLocked());
    }

    public function testSetTitleReturnsSelf(): void
    {
        $result = $this->decision->setTitle('Test');

        $this->assertSame($this->decision, $result);
    }

    public function testSetDescriptionReturnsSelf(): void
    {
        $result = $this->decision->setDescription('desc');

        $this->assertSame($this->decision, $result);
    }

    public function testSetStatusReturnsSelf(): void
    {
        $result = $this->decision->setStatus(Decision::STATUS_OUVERT);

        $this->assertSame($this->decision, $result);
    }

    public function testSetOptionsReturnsSelf(): void
    {
        $result = $this->decision->setOptions([]);

        $this->assertSame($this->decision, $result);
    }

    public function testAddOptionReturnsSelf(): void
    {
        $result = $this->decision->addOption('test');

        $this->assertSame($this->decision, $result);
    }

    public function testSetSessionReturnsSelf(): void
    {
        $session = new Session();

        $result = $this->decision->setSession($session);

        $this->assertSame($this->decision, $result);
    }

    public function testSetLinkedDocumentReturnsSelf(): void
    {
        $result = $this->decision->setLinkedDocument(null);

        $this->assertSame($this->decision, $result);
    }

    public function testAddVoteReturnsSelf(): void
    {
        $vote = new Vote();

        $result = $this->decision->addVote($vote);

        $this->assertSame($this->decision, $result);
    }

    public function testRemoveVoteReturnsSelf(): void
    {
        $vote = new Vote();
        $this->decision->addVote($vote);

        $result = $this->decision->removeVote($vote);

        $this->assertSame($this->decision, $result);
    }

    public function testSetSelectedOptionIdReturnsSelf(): void
    {
        $result = $this->decision->setSelectedOptionId(Uuid::v7());

        $this->assertSame($this->decision, $result);
    }

    public function testSetIsLockedReturnsSelf(): void
    {
        $result = $this->decision->setIsLocked(true);

        $this->assertSame($this->decision, $result);
    }

    public function testGetVoteStatsWithMultipleVotesPerOption(): void
    {
        $this->decision->addOption('Popular');
        $this->decision->addOption('Unpopular');

        $options = $this->decision->getOptions();
        $popularId = Uuid::fromString($options[0]['id']);

        $session = new Session();
        $session->setTitle('Test');

        for ($i = 0; $i < 10; $i++) {
            $participant = new Participant();
            $participant->setSession($session);
            $participant->setPseudo("User$i");

            $vote = new Vote();
            $vote->setParticipant($participant);
            $vote->setOptionId($popularId);
            $this->decision->addVote($vote);
        }

        $stats = $this->decision->getVoteStats();

        $this->assertSame(10, $stats[$options[0]['id']]);
        $this->assertSame(0, $stats[$options[1]['id']]);
    }

    public function testMultipleDecisionsHaveUniqueIds(): void
    {
        $decision1 = new Decision();
        $decision2 = new Decision();

        $this->assertFalse($decision1->getId()->equals($decision2->getId()));
    }

    public function testOptionsWithSameLabel(): void
    {
        $this->decision->addOption('Same Label');
        $this->decision->addOption('Same Label');

        $options = $this->decision->getOptions();

        $this->assertCount(2, $options);
        $this->assertNotSame($options[0]['id'], $options[1]['id']);
    }
}

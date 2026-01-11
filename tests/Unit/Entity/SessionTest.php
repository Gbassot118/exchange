<?php

namespace App\Tests\Unit\Entity;

use App\Entity\Decision;
use App\Entity\Document;
use App\Entity\Participant;
use App\Entity\Session;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Uid\Uuid;

class SessionTest extends TestCase
{
    private Session $session;

    protected function setUp(): void
    {
        $this->session = new Session();
    }

    public function testConstructorGeneratesUuidV7(): void
    {
        $id = $this->session->getId();

        $this->assertInstanceOf(Uuid::class, $id);
        // UUIDv7 has version 7 in the 13th character position
        $this->assertMatchesRegularExpression('/^[0-9a-f]{8}-[0-9a-f]{4}-7[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i', $id->toString());
    }

    public function testConstructorGeneratesInviteCode(): void
    {
        $inviteCode = $this->session->getInviteCode();

        $this->assertNotEmpty($inviteCode);
    }

    public function testInviteCodeHas32Characters(): void
    {
        $inviteCode = $this->session->getInviteCode();

        $this->assertSame(32, strlen($inviteCode));
    }

    public function testInviteCodeIsHexadecimal(): void
    {
        $inviteCode = $this->session->getInviteCode();

        $this->assertMatchesRegularExpression('/^[0-9a-f]{32}$/', $inviteCode);
    }

    public function testConstructorSetsCreatedAtToNow(): void
    {
        $createdAt = $this->session->getCreatedAt();

        $this->assertInstanceOf(\DateTimeImmutable::class, $createdAt);
        $this->assertEqualsWithDelta(new \DateTimeImmutable(), $createdAt, 1);
    }

    public function testConstructorSetsUpdatedAtToNow(): void
    {
        $updatedAt = $this->session->getUpdatedAt();

        $this->assertInstanceOf(\DateTimeImmutable::class, $updatedAt);
        $this->assertEqualsWithDelta(new \DateTimeImmutable(), $updatedAt, 1);
    }

    public function testDefaultStatusIsPreparation(): void
    {
        $this->assertSame(Session::STATUS_PREPARATION, $this->session->getStatus());
    }

    public function testCollectionsAreInitializedEmpty(): void
    {
        $this->assertCount(0, $this->session->getDocuments());
        $this->assertCount(0, $this->session->getParticipants());
        $this->assertCount(0, $this->session->getDecisions());
    }

    public function testSetAndGetTitle(): void
    {
        $this->session->setTitle('Test Session');

        $this->assertSame('Test Session', $this->session->getTitle());
    }

    public function testSetAndGetDescription(): void
    {
        $description = 'This is a session description';

        $this->session->setDescription($description);

        $this->assertSame($description, $this->session->getDescription());
    }

    public function testSetAndGetDescriptionWithNull(): void
    {
        $this->session->setDescription('Some description');
        $this->session->setDescription(null);

        $this->assertNull($this->session->getDescription());
    }

    public function testDescriptionDefaultIsNull(): void
    {
        $this->assertNull($this->session->getDescription());
    }

    public function testSetAndGetStatus(): void
    {
        $this->session->setStatus(Session::STATUS_EN_COURS);

        $this->assertSame(Session::STATUS_EN_COURS, $this->session->getStatus());
    }

    public function testSetAndGetInviteCode(): void
    {
        $newCode = 'abc123def456abc123def456abc12345';

        $this->session->setInviteCode($newCode);

        $this->assertSame($newCode, $this->session->getInviteCode());
    }

    public function testAddDocumentAddsToCollection(): void
    {
        $document = new Document();
        $document->setTitle('Test Doc');
        $document->setSlug('test-doc');

        $this->session->addDocument($document);

        $this->assertCount(1, $this->session->getDocuments());
        $this->assertTrue($this->session->getDocuments()->contains($document));
    }

    public function testAddDocumentSetsSessionOnDocument(): void
    {
        $document = new Document();
        $document->setTitle('Test Doc');
        $document->setSlug('test-doc');

        $this->session->addDocument($document);

        $this->assertSame($this->session, $document->getSession());
    }

    public function testAddDocumentDoesNotAddDuplicate(): void
    {
        $document = new Document();
        $document->setTitle('Test Doc');
        $document->setSlug('test-doc');

        $this->session->addDocument($document);
        $this->session->addDocument($document);

        $this->assertCount(1, $this->session->getDocuments());
    }

    public function testRemoveDocumentRemovesFromCollection(): void
    {
        $document = new Document();
        $document->setTitle('Test Doc');
        $document->setSlug('test-doc');

        $this->session->addDocument($document);
        $this->session->removeDocument($document);

        $this->assertCount(0, $this->session->getDocuments());
    }

    public function testAddParticipantAddsToCollection(): void
    {
        $participant = new Participant();
        $participant->setPseudo('TestUser');

        $this->session->addParticipant($participant);

        $this->assertCount(1, $this->session->getParticipants());
        $this->assertTrue($this->session->getParticipants()->contains($participant));
    }

    public function testAddParticipantSetsSessionOnParticipant(): void
    {
        $participant = new Participant();
        $participant->setPseudo('TestUser');

        $this->session->addParticipant($participant);

        $this->assertSame($this->session, $participant->getSession());
    }

    public function testAddParticipantDoesNotAddDuplicate(): void
    {
        $participant = new Participant();
        $participant->setPseudo('TestUser');

        $this->session->addParticipant($participant);
        $this->session->addParticipant($participant);

        $this->assertCount(1, $this->session->getParticipants());
    }

    public function testRemoveParticipantRemovesFromCollection(): void
    {
        $participant = new Participant();
        $participant->setPseudo('TestUser');

        $this->session->addParticipant($participant);
        $this->session->removeParticipant($participant);

        $this->assertCount(0, $this->session->getParticipants());
    }

    public function testAddDecisionAddsToCollection(): void
    {
        $decision = new Decision();
        $decision->setTitle('Test Decision');

        $this->session->addDecision($decision);

        $this->assertCount(1, $this->session->getDecisions());
        $this->assertTrue($this->session->getDecisions()->contains($decision));
    }

    public function testAddDecisionSetsSessionOnDecision(): void
    {
        $decision = new Decision();
        $decision->setTitle('Test Decision');

        $this->session->addDecision($decision);

        $this->assertSame($this->session, $decision->getSession());
    }

    public function testAddDecisionDoesNotAddDuplicate(): void
    {
        $decision = new Decision();
        $decision->setTitle('Test Decision');

        $this->session->addDecision($decision);
        $this->session->addDecision($decision);

        $this->assertCount(1, $this->session->getDecisions());
    }

    public function testRemoveDecisionRemovesFromCollection(): void
    {
        $decision = new Decision();
        $decision->setTitle('Test Decision');

        $this->session->addDecision($decision);
        $this->session->removeDecision($decision);

        $this->assertCount(0, $this->session->getDecisions());
    }

    public function testStatusConstants(): void
    {
        $this->assertSame('preparation', Session::STATUS_PREPARATION);
        $this->assertSame('en_cours', Session::STATUS_EN_COURS);
        $this->assertSame('termine', Session::STATUS_TERMINE);
        $this->assertSame('archive', Session::STATUS_ARCHIVE);
    }

    public function testMultipleDocumentsCanBeAdded(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $document = new Document();
            $document->setTitle("Doc $i");
            $document->setSlug("doc-$i");
            $this->session->addDocument($document);
        }

        $this->assertCount(5, $this->session->getDocuments());
    }

    public function testMultipleParticipantsCanBeAdded(): void
    {
        for ($i = 0; $i < 10; $i++) {
            $participant = new Participant();
            $participant->setPseudo("User$i");
            $this->session->addParticipant($participant);
        }

        $this->assertCount(10, $this->session->getParticipants());
    }

    public function testMultipleDecisionsCanBeAdded(): void
    {
        for ($i = 0; $i < 3; $i++) {
            $decision = new Decision();
            $decision->setTitle("Decision $i");
            $this->session->addDecision($decision);
        }

        $this->assertCount(3, $this->session->getDecisions());
    }

    public function testSetTitleReturnsSelf(): void
    {
        $result = $this->session->setTitle('Test');

        $this->assertSame($this->session, $result);
    }

    public function testSetDescriptionReturnsSelf(): void
    {
        $result = $this->session->setDescription('desc');

        $this->assertSame($this->session, $result);
    }

    public function testSetStatusReturnsSelf(): void
    {
        $result = $this->session->setStatus(Session::STATUS_EN_COURS);

        $this->assertSame($this->session, $result);
    }

    public function testSetInviteCodeReturnsSelf(): void
    {
        $result = $this->session->setInviteCode('code');

        $this->assertSame($this->session, $result);
    }

    public function testAddDocumentReturnsSelf(): void
    {
        $document = new Document();

        $result = $this->session->addDocument($document);

        $this->assertSame($this->session, $result);
    }

    public function testRemoveDocumentReturnsSelf(): void
    {
        $document = new Document();
        $this->session->addDocument($document);

        $result = $this->session->removeDocument($document);

        $this->assertSame($this->session, $result);
    }

    public function testAddParticipantReturnsSelf(): void
    {
        $participant = new Participant();

        $result = $this->session->addParticipant($participant);

        $this->assertSame($this->session, $result);
    }

    public function testRemoveParticipantReturnsSelf(): void
    {
        $participant = new Participant();
        $this->session->addParticipant($participant);

        $result = $this->session->removeParticipant($participant);

        $this->assertSame($this->session, $result);
    }

    public function testAddDecisionReturnsSelf(): void
    {
        $decision = new Decision();

        $result = $this->session->addDecision($decision);

        $this->assertSame($this->session, $result);
    }

    public function testRemoveDecisionReturnsSelf(): void
    {
        $decision = new Decision();
        $this->session->addDecision($decision);

        $result = $this->session->removeDecision($decision);

        $this->assertSame($this->session, $result);
    }

    public function testMultipleSessionsHaveUniqueIds(): void
    {
        $session1 = new Session();
        $session2 = new Session();

        $this->assertFalse($session1->getId()->equals($session2->getId()));
    }

    public function testMultipleSessionsHaveUniqueInviteCodes(): void
    {
        $session1 = new Session();
        $session2 = new Session();

        $this->assertNotSame($session1->getInviteCode(), $session2->getInviteCode());
    }

    public function testTitleCanContainSpecialCharacters(): void
    {
        $title = "Session avec caractères spéciaux: éàü & <tag> \"quotes\"";

        $this->session->setTitle($title);

        $this->assertSame($title, $this->session->getTitle());
    }

    public function testDescriptionCanBeMultiline(): void
    {
        $description = "Line 1\nLine 2\nLine 3\n\nParagraph 2";

        $this->session->setDescription($description);

        $this->assertSame($description, $this->session->getDescription());
    }

    public function testAllStatusValuesCanBeSet(): void
    {
        $statuses = [
            Session::STATUS_PREPARATION,
            Session::STATUS_EN_COURS,
            Session::STATUS_TERMINE,
            Session::STATUS_ARCHIVE,
        ];

        foreach ($statuses as $status) {
            $this->session->setStatus($status);
            $this->assertSame($status, $this->session->getStatus());
        }
    }
}

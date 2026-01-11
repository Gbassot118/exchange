<?php

namespace App\Tests\Unit\Entity;

use App\Entity\Participant;
use App\Entity\Session;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Uid\Uuid;

class ParticipantTest extends TestCase
{
    private const VALID_COLORS = [
        '#3B82F6', '#EF4444', '#10B981', '#F59E0B', '#8B5CF6',
        '#EC4899', '#06B6D4', '#84CC16', '#F97316', '#6366F1',
    ];

    private Participant $participant;

    protected function setUp(): void
    {
        $this->participant = new Participant();
    }

    public function testConstructorGeneratesUuidV7(): void
    {
        $id = $this->participant->getId();

        $this->assertInstanceOf(Uuid::class, $id);
        // UUIDv7 has version 7 in the 13th character position
        $this->assertMatchesRegularExpression('/^[0-9a-f]{8}-[0-9a-f]{4}-7[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i', $id->toString());
    }

    public function testConstructorAssignsRandomColor(): void
    {
        $color = $this->participant->getColor();

        $this->assertNotEmpty($color);
        $this->assertMatchesRegularExpression('/^#[0-9A-Fa-f]{6}$/', $color);
    }

    public function testColorIsFromPredefinedPalette(): void
    {
        $color = $this->participant->getColor();

        $this->assertContains($color, self::VALID_COLORS);
    }

    public function testDefaultIsAgentIsFalse(): void
    {
        $this->assertFalse($this->participant->isAgent());
    }

    public function testDefaultLastSeenAtIsNull(): void
    {
        $this->assertNull($this->participant->getLastSeenAt());
    }

    public function testConstructorSetsCreatedAt(): void
    {
        $createdAt = $this->participant->getCreatedAt();

        $this->assertInstanceOf(\DateTimeImmutable::class, $createdAt);
        $this->assertEqualsWithDelta(new \DateTimeImmutable(), $createdAt, 1);
    }

    public function testIsOnlineReturnsFalseWhenLastSeenAtIsNull(): void
    {
        $this->assertFalse($this->participant->isOnline());
    }

    public function testIsOnlineReturnsTrueWhenSeenRecently(): void
    {
        $this->participant->setLastSeenAt(new \DateTimeImmutable('-10 seconds'));

        $this->assertTrue($this->participant->isOnline());
    }

    public function testIsOnlineReturnsFalseWhenSeenMoreThan30SecondsAgo(): void
    {
        $this->participant->setLastSeenAt(new \DateTimeImmutable('-35 seconds'));

        $this->assertFalse($this->participant->isOnline());
    }

    public function testIsOnlineBoundaryCondition29Seconds(): void
    {
        $this->participant->setLastSeenAt(new \DateTimeImmutable('-29 seconds'));

        $this->assertTrue($this->participant->isOnline());
    }

    public function testIsOnlineBoundaryCondition31Seconds(): void
    {
        $this->participant->setLastSeenAt(new \DateTimeImmutable('-31 seconds'));

        $this->assertFalse($this->participant->isOnline());
    }

    public function testIsOnlineAtExactly30Seconds(): void
    {
        // At exactly 30 seconds, it should still be online (> comparison, not >=)
        $this->participant->setLastSeenAt(new \DateTimeImmutable('-30 seconds'));

        // The comparison is: lastSeenAt > new DateTimeImmutable('-30 seconds')
        // So at exactly -30 seconds, it's NOT online (not strictly greater)
        $this->assertFalse($this->participant->isOnline());
    }

    public function testSetAndGetPseudo(): void
    {
        $this->participant->setPseudo('JohnDoe');

        $this->assertSame('JohnDoe', $this->participant->getPseudo());
    }

    public function testSetAndGetSession(): void
    {
        $session = new Session();
        $session->setTitle('Test Session');

        $this->participant->setSession($session);

        $this->assertSame($session, $this->participant->getSession());
    }

    public function testSetAndGetColor(): void
    {
        $this->participant->setColor('#FF0000');

        $this->assertSame('#FF0000', $this->participant->getColor());
    }

    public function testSetAndGetCurrentDocumentId(): void
    {
        $documentId = Uuid::v7();

        $this->participant->setCurrentDocumentId($documentId);

        $this->assertSame($documentId, $this->participant->getCurrentDocumentId());
    }

    public function testCurrentDocumentIdCanBeNull(): void
    {
        $this->participant->setCurrentDocumentId(Uuid::v7());
        $this->participant->setCurrentDocumentId(null);

        $this->assertNull($this->participant->getCurrentDocumentId());
    }

    public function testCurrentDocumentIdDefaultIsNull(): void
    {
        $this->assertNull($this->participant->getCurrentDocumentId());
    }

    public function testSetAndGetIsAgent(): void
    {
        $this->participant->setIsAgent(true);

        $this->assertTrue($this->participant->isAgent());
    }

    public function testSetIsAgentToFalse(): void
    {
        $this->participant->setIsAgent(true);
        $this->participant->setIsAgent(false);

        $this->assertFalse($this->participant->isAgent());
    }

    public function testSetAndGetLastSeenAt(): void
    {
        $now = new \DateTimeImmutable();

        $this->participant->setLastSeenAt($now);

        $this->assertSame($now, $this->participant->getLastSeenAt());
    }

    public function testGetAnnotationsReturnsCollection(): void
    {
        $annotations = $this->participant->getAnnotations();

        $this->assertCount(0, $annotations);
    }

    public function testGetVotesReturnsCollection(): void
    {
        $votes = $this->participant->getVotes();

        $this->assertCount(0, $votes);
    }

    public function testPseudoCanContainSpecialCharacters(): void
    {
        $pseudo = 'User-Name_123 (Test)';

        $this->participant->setPseudo($pseudo);

        $this->assertSame($pseudo, $this->participant->getPseudo());
    }

    public function testPseudoCanContainUnicodeCharacters(): void
    {
        $pseudo = 'Utilisateur Français 日本語';

        $this->participant->setPseudo($pseudo);

        $this->assertSame($pseudo, $this->participant->getPseudo());
    }

    public function testSetPseudoReturnsSelf(): void
    {
        $result = $this->participant->setPseudo('Test');

        $this->assertSame($this->participant, $result);
    }

    public function testSetSessionReturnsSelf(): void
    {
        $session = new Session();

        $result = $this->participant->setSession($session);

        $this->assertSame($this->participant, $result);
    }

    public function testSetColorReturnsSelf(): void
    {
        $result = $this->participant->setColor('#000000');

        $this->assertSame($this->participant, $result);
    }

    public function testSetIsAgentReturnsSelf(): void
    {
        $result = $this->participant->setIsAgent(true);

        $this->assertSame($this->participant, $result);
    }

    public function testSetLastSeenAtReturnsSelf(): void
    {
        $result = $this->participant->setLastSeenAt(new \DateTimeImmutable());

        $this->assertSame($this->participant, $result);
    }

    public function testSetCurrentDocumentIdReturnsSelf(): void
    {
        $result = $this->participant->setCurrentDocumentId(Uuid::v7());

        $this->assertSame($this->participant, $result);
    }

    public function testMultipleParticipantsHaveUniqueIds(): void
    {
        $participant1 = new Participant();
        $participant2 = new Participant();

        $this->assertFalse($participant1->getId()->equals($participant2->getId()));
    }

    public function testMultipleParticipantsCanHaveSameColor(): void
    {
        // Due to randomness, create many participants and check colors are from palette
        $colors = [];
        for ($i = 0; $i < 50; $i++) {
            $p = new Participant();
            $colors[] = $p->getColor();
        }

        foreach ($colors as $color) {
            $this->assertContains($color, self::VALID_COLORS);
        }
    }

    public function testColorFormatIsValidHexadecimal(): void
    {
        $color = $this->participant->getColor();

        $this->assertSame(7, strlen($color));
        $this->assertSame('#', $color[0]);
    }
}

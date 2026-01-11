<?php

namespace App\Tests\Unit\Domain\Session\ValueObject;

use App\Domain\Session\Exception\InvalidSessionStatusTransitionException;
use App\Domain\Session\ValueObject\SessionStatus;
use PHPUnit\Framework\TestCase;

class SessionStatusTest extends TestCase
{
    public function testPreparationStatus(): void
    {
        $status = SessionStatus::preparation();

        $this->assertTrue($status->isPreparation());
        $this->assertFalse($status->isEnCours());
        $this->assertFalse($status->isTermine());
        $this->assertFalse($status->isArchive());
        $this->assertSame('preparation', $status->toString());
    }

    public function testEnCoursStatus(): void
    {
        $status = SessionStatus::enCours();

        $this->assertFalse($status->isPreparation());
        $this->assertTrue($status->isEnCours());
        $this->assertFalse($status->isTermine());
        $this->assertFalse($status->isArchive());
        $this->assertSame('en_cours', $status->toString());
    }

    public function testTermineStatus(): void
    {
        $status = SessionStatus::termine();

        $this->assertFalse($status->isPreparation());
        $this->assertFalse($status->isEnCours());
        $this->assertTrue($status->isTermine());
        $this->assertFalse($status->isArchive());
        $this->assertSame('termine', $status->toString());
    }

    public function testArchiveStatus(): void
    {
        $status = SessionStatus::archive();

        $this->assertFalse($status->isPreparation());
        $this->assertFalse($status->isEnCours());
        $this->assertFalse($status->isTermine());
        $this->assertTrue($status->isArchive());
        $this->assertSame('archive', $status->toString());
    }

    public function testFromStringWithValidValues(): void
    {
        $this->assertTrue(SessionStatus::fromString('preparation')->isPreparation());
        $this->assertTrue(SessionStatus::fromString('en_cours')->isEnCours());
        $this->assertTrue(SessionStatus::fromString('termine')->isTermine());
        $this->assertTrue(SessionStatus::fromString('archive')->isArchive());
    }

    public function testFromStringWithInvalidValue(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid session status "invalid"');

        SessionStatus::fromString('invalid');
    }

    public function testValidTransitions(): void
    {
        // preparation -> en_cours
        $this->assertTrue(SessionStatus::preparation()->canTransitionTo(SessionStatus::enCours()));

        // en_cours -> termine
        $this->assertTrue(SessionStatus::enCours()->canTransitionTo(SessionStatus::termine()));

        // en_cours -> preparation (retour possible)
        $this->assertTrue(SessionStatus::enCours()->canTransitionTo(SessionStatus::preparation()));

        // termine -> archive
        $this->assertTrue(SessionStatus::termine()->canTransitionTo(SessionStatus::archive()));

        // termine -> en_cours (retour possible)
        $this->assertTrue(SessionStatus::termine()->canTransitionTo(SessionStatus::enCours()));
    }

    public function testInvalidTransitions(): void
    {
        // preparation -> termine (skip)
        $this->assertFalse(SessionStatus::preparation()->canTransitionTo(SessionStatus::termine()));

        // preparation -> archive (skip)
        $this->assertFalse(SessionStatus::preparation()->canTransitionTo(SessionStatus::archive()));

        // archive -> any (no transitions from archive)
        $this->assertFalse(SessionStatus::archive()->canTransitionTo(SessionStatus::preparation()));
        $this->assertFalse(SessionStatus::archive()->canTransitionTo(SessionStatus::enCours()));
        $this->assertFalse(SessionStatus::archive()->canTransitionTo(SessionStatus::termine()));
    }

    public function testSameStatusTransitionIsAllowed(): void
    {
        $status = SessionStatus::preparation();
        $this->assertTrue($status->canTransitionTo(SessionStatus::preparation()));
    }

    public function testValidateTransitionToThrowsOnInvalidTransition(): void
    {
        $this->expectException(InvalidSessionStatusTransitionException::class);

        SessionStatus::preparation()->validateTransitionTo(SessionStatus::archive());
    }

    public function testValidateTransitionToSucceedsOnValidTransition(): void
    {
        SessionStatus::preparation()->validateTransitionTo(SessionStatus::enCours());
        $this->assertTrue(true); // No exception thrown
    }

    public function testEquals(): void
    {
        $status1 = SessionStatus::preparation();
        $status2 = SessionStatus::preparation();
        $status3 = SessionStatus::enCours();

        $this->assertTrue($status1->equals($status2));
        $this->assertFalse($status1->equals($status3));
    }

    public function testAllStatuses(): void
    {
        $statuses = SessionStatus::allStatuses();

        $this->assertCount(4, $statuses);
        $this->assertContains('preparation', $statuses);
        $this->assertContains('en_cours', $statuses);
        $this->assertContains('termine', $statuses);
        $this->assertContains('archive', $statuses);
    }

    public function testToString(): void
    {
        $status = SessionStatus::enCours();

        $this->assertSame('en_cours', (string) $status);
    }
}

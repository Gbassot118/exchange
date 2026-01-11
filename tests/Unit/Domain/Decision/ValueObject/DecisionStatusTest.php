<?php

namespace App\Tests\Unit\Domain\Decision\ValueObject;

use App\Domain\Decision\ValueObject\DecisionStatus;
use PHPUnit\Framework\TestCase;

class DecisionStatusTest extends TestCase
{
    public function testOuvertStatus(): void
    {
        $status = DecisionStatus::ouvert();

        $this->assertTrue($status->isOuvert());
        $this->assertFalse($status->isEnDiscussion());
        $this->assertFalse($status->isConsensus());
        $this->assertFalse($status->isValide());
        $this->assertFalse($status->isReporte());
        $this->assertSame('ouvert', $status->toString());
    }

    public function testEnDiscussionStatus(): void
    {
        $status = DecisionStatus::enDiscussion();

        $this->assertTrue($status->isEnDiscussion());
        $this->assertSame('en_discussion', $status->toString());
    }

    public function testConsensusStatus(): void
    {
        $status = DecisionStatus::consensus();

        $this->assertTrue($status->isConsensus());
        $this->assertSame('consensus', $status->toString());
    }

    public function testValideStatus(): void
    {
        $status = DecisionStatus::valide();

        $this->assertTrue($status->isValide());
        $this->assertSame('valide', $status->toString());
    }

    public function testReporteStatus(): void
    {
        $status = DecisionStatus::reporte();

        $this->assertTrue($status->isReporte());
        $this->assertSame('reporte', $status->toString());
    }

    public function testIsPending(): void
    {
        $this->assertTrue(DecisionStatus::ouvert()->isPending());
        $this->assertTrue(DecisionStatus::enDiscussion()->isPending());
        $this->assertFalse(DecisionStatus::consensus()->isPending());
        $this->assertFalse(DecisionStatus::valide()->isPending());
        $this->assertFalse(DecisionStatus::reporte()->isPending());
    }

    public function testIsFinal(): void
    {
        $this->assertFalse(DecisionStatus::ouvert()->isFinal());
        $this->assertFalse(DecisionStatus::enDiscussion()->isFinal());
        $this->assertFalse(DecisionStatus::consensus()->isFinal());
        $this->assertTrue(DecisionStatus::valide()->isFinal());
        $this->assertTrue(DecisionStatus::reporte()->isFinal());
    }

    public function testFromStringWithValidValues(): void
    {
        $this->assertTrue(DecisionStatus::fromString('ouvert')->isOuvert());
        $this->assertTrue(DecisionStatus::fromString('en_discussion')->isEnDiscussion());
        $this->assertTrue(DecisionStatus::fromString('consensus')->isConsensus());
        $this->assertTrue(DecisionStatus::fromString('valide')->isValide());
        $this->assertTrue(DecisionStatus::fromString('reporte')->isReporte());
    }

    public function testFromStringWithInvalidValue(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid decision status "invalid"');

        DecisionStatus::fromString('invalid');
    }

    public function testEquals(): void
    {
        $status1 = DecisionStatus::ouvert();
        $status2 = DecisionStatus::ouvert();
        $status3 = DecisionStatus::valide();

        $this->assertTrue($status1->equals($status2));
        $this->assertFalse($status1->equals($status3));
    }

    public function testAllStatuses(): void
    {
        $statuses = DecisionStatus::allStatuses();

        $this->assertCount(5, $statuses);
        $this->assertContains('ouvert', $statuses);
        $this->assertContains('en_discussion', $statuses);
        $this->assertContains('consensus', $statuses);
        $this->assertContains('valide', $statuses);
        $this->assertContains('reporte', $statuses);
    }

    public function testToString(): void
    {
        $status = DecisionStatus::ouvert();

        $this->assertSame('ouvert', (string) $status);
    }
}

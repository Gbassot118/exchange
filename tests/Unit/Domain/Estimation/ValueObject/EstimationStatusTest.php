<?php

namespace App\Tests\Unit\Domain\Estimation\ValueObject;

use App\Domain\Estimation\ValueObject\EstimationStatus;
use PHPUnit\Framework\TestCase;

class EstimationStatusTest extends TestCase
{
    public function testOpenStatus(): void
    {
        $status = EstimationStatus::open();

        $this->assertTrue($status->isOpen());
        $this->assertFalse($status->isRevealed());
        $this->assertFalse($status->isClosed());
        $this->assertSame('open', $status->toString());
    }

    public function testRevealedStatus(): void
    {
        $status = EstimationStatus::revealed();

        $this->assertFalse($status->isOpen());
        $this->assertTrue($status->isRevealed());
        $this->assertFalse($status->isClosed());
        $this->assertSame('revealed', $status->toString());
    }

    public function testClosedStatus(): void
    {
        $status = EstimationStatus::closed();

        $this->assertFalse($status->isOpen());
        $this->assertFalse($status->isRevealed());
        $this->assertTrue($status->isClosed());
        $this->assertSame('closed', $status->toString());
    }

    public function testCanVote(): void
    {
        $this->assertTrue(EstimationStatus::open()->canVote());
        $this->assertFalse(EstimationStatus::revealed()->canVote());
        $this->assertFalse(EstimationStatus::closed()->canVote());
    }

    public function testVotesVisible(): void
    {
        $this->assertFalse(EstimationStatus::open()->votesVisible());
        $this->assertTrue(EstimationStatus::revealed()->votesVisible());
        $this->assertTrue(EstimationStatus::closed()->votesVisible());
    }

    public function testFromStringWithValidValues(): void
    {
        $this->assertTrue(EstimationStatus::fromString('open')->isOpen());
        $this->assertTrue(EstimationStatus::fromString('revealed')->isRevealed());
        $this->assertTrue(EstimationStatus::fromString('closed')->isClosed());
    }

    public function testFromStringWithInvalidValue(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid estimation status "invalid"');

        EstimationStatus::fromString('invalid');
    }

    public function testEquals(): void
    {
        $status1 = EstimationStatus::open();
        $status2 = EstimationStatus::open();
        $status3 = EstimationStatus::revealed();

        $this->assertTrue($status1->equals($status2));
        $this->assertFalse($status1->equals($status3));
    }

    public function testAllStatuses(): void
    {
        $statuses = EstimationStatus::allStatuses();

        $this->assertCount(3, $statuses);
        $this->assertContains('open', $statuses);
        $this->assertContains('revealed', $statuses);
        $this->assertContains('closed', $statuses);
    }

    public function testToString(): void
    {
        $status = EstimationStatus::open();

        $this->assertSame('open', (string) $status);
    }
}

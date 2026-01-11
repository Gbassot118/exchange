<?php

namespace App\Tests\Unit\Entity;

use App\Entity\Estimation;
use App\Entity\EstimationVote;
use App\Entity\Participant;
use App\Entity\Session;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Uid\Uuid;

class EstimationVoteTest extends TestCase
{
    private EstimationVote $vote;

    protected function setUp(): void
    {
        $this->vote = new EstimationVote();
    }

    public function testConstructorGeneratesUuidV7(): void
    {
        $id = $this->vote->getId();

        $this->assertInstanceOf(Uuid::class, $id);
        $this->assertMatchesRegularExpression('/^[0-9a-f]{8}-[0-9a-f]{4}-7[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i', $id->toString());
    }

    public function testConstructorSetsCreatedAt(): void
    {
        $createdAt = $this->vote->getCreatedAt();

        $this->assertInstanceOf(\DateTimeImmutable::class, $createdAt);
        $this->assertEqualsWithDelta(new \DateTimeImmutable(), $createdAt, 1);
    }

    public function testSetAndGetEstimation(): void
    {
        $session = new Session();
        $session->setTitle('Test Session');

        $estimation = new Estimation();
        $estimation->setSession($session);
        $estimation->setTitle('Test Estimation');

        $this->vote->setEstimation($estimation);

        $this->assertSame($estimation, $this->vote->getEstimation());
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

    public function testSetAndGetValue(): void
    {
        $this->vote->setValue('5');

        $this->assertSame('5', $this->vote->getValue());
    }

    #[DataProvider('fibonacciValuesProvider')]
    public function testSetValueWithValidFibonacciValues(string $value): void
    {
        $this->vote->setValue($value);

        $this->assertSame($value, $this->vote->getValue());
    }

    public static function fibonacciValuesProvider(): array
    {
        return [
            ['0'],
            ['1'],
            ['2'],
            ['3'],
            ['5'],
            ['8'],
            ['13'],
            ['21'],
            ['?'],
        ];
    }

    public function testSetEstimationReturnsSelf(): void
    {
        $estimation = new Estimation();

        $result = $this->vote->setEstimation($estimation);

        $this->assertSame($this->vote, $result);
    }

    public function testSetParticipantReturnsSelf(): void
    {
        $participant = new Participant();

        $result = $this->vote->setParticipant($participant);

        $this->assertSame($this->vote, $result);
    }

    public function testSetValueReturnsSelf(): void
    {
        $result = $this->vote->setValue('5');

        $this->assertSame($this->vote, $result);
    }

    public function testMultipleVotesHaveUniqueIds(): void
    {
        $vote1 = new EstimationVote();
        $vote2 = new EstimationVote();
        $vote3 = new EstimationVote();

        $this->assertFalse($vote1->getId()->equals($vote2->getId()));
        $this->assertFalse($vote2->getId()->equals($vote3->getId()));
        $this->assertFalse($vote1->getId()->equals($vote3->getId()));
    }

    public function testValueCanBeQuestionMark(): void
    {
        $this->vote->setValue('?');

        $this->assertSame('?', $this->vote->getValue());
    }

    public function testValueCanBeZero(): void
    {
        $this->vote->setValue('0');

        $this->assertSame('0', $this->vote->getValue());
    }
}

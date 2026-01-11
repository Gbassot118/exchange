<?php

namespace App\Tests\Unit\Service\Decision;

use App\Entity\Decision;
use App\Entity\Document;
use App\Entity\Participant;
use App\Entity\Session;
use App\Entity\Vote;
use App\Repository\DecisionRepository;
use App\Repository\VoteRepository;
use App\Service\Decision\DecisionService;
use App\Service\Mercure\MercurePublisher;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Uid\Uuid;

class DecisionServiceTest extends TestCase
{
    private EntityManagerInterface&MockObject $entityManager;
    private DecisionRepository&MockObject $decisionRepository;
    private VoteRepository&MockObject $voteRepository;
    private MercurePublisher&MockObject $mercurePublisher;
    private DecisionService $service;

    protected function setUp(): void
    {
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->decisionRepository = $this->createMock(DecisionRepository::class);
        $this->voteRepository = $this->createMock(VoteRepository::class);
        $this->mercurePublisher = $this->createMock(MercurePublisher::class);

        $this->service = new DecisionService(
            $this->entityManager,
            $this->decisionRepository,
            $this->voteRepository,
            $this->mercurePublisher
        );
    }

    private function createSession(): Session
    {
        $session = new Session();
        $session->setTitle('Test Session');
        return $session;
    }

    private function createParticipant(Session $session): Participant
    {
        $participant = new Participant();
        $participant->setSession($session);
        $participant->setPseudo('TestUser');
        return $participant;
    }

    public function testCreateReturnsDecision(): void
    {
        $session = $this->createSession();

        $this->decisionRepository->method('save');
        $this->mercurePublisher->method('publishDecisionCreated');

        $decision = $this->service->create($session, 'Test Decision', [
            ['label' => 'Option A'],
            ['label' => 'Option B'],
        ]);

        $this->assertInstanceOf(Decision::class, $decision);
    }

    public function testCreateSetsSession(): void
    {
        $session = $this->createSession();

        $this->decisionRepository->method('save');
        $this->mercurePublisher->method('publishDecisionCreated');

        $decision = $this->service->create($session, 'Test', [['label' => 'A']]);

        $this->assertSame($session, $decision->getSession());
    }

    public function testCreateSetsTitle(): void
    {
        $session = $this->createSession();

        $this->decisionRepository->method('save');
        $this->mercurePublisher->method('publishDecisionCreated');

        $decision = $this->service->create($session, 'My Decision Title', [['label' => 'A']]);

        $this->assertSame('My Decision Title', $decision->getTitle());
    }

    public function testCreateSetsDescription(): void
    {
        $session = $this->createSession();

        $this->decisionRepository->method('save');
        $this->mercurePublisher->method('publishDecisionCreated');

        $decision = $this->service->create($session, 'Title', [['label' => 'A']], 'Description here');

        $this->assertSame('Description here', $decision->getDescription());
    }

    public function testCreateSetsLinkedDocument(): void
    {
        $session = $this->createSession();
        $document = new Document();
        $document->setSession($session);
        $document->setTitle('Doc');
        $document->setSlug('doc');

        $this->decisionRepository->method('save');
        $this->mercurePublisher->method('publishDecisionCreated');

        $decision = $this->service->create($session, 'Title', [['label' => 'A']], null, $document);

        $this->assertSame($document, $decision->getLinkedDocument());
    }

    public function testCreateFormatsOptions(): void
    {
        $session = $this->createSession();

        $this->decisionRepository->method('save');
        $this->mercurePublisher->method('publishDecisionCreated');

        $decision = $this->service->create($session, 'Title', [
            ['label' => 'Option A', 'description' => 'Desc A'],
            ['label' => 'Option B'],
        ]);

        $options = $decision->getOptions();
        $this->assertCount(2, $options);
        $this->assertSame('Option A', $options[0]['label']);
        $this->assertSame('Desc A', $options[0]['description']);
        $this->assertSame('Option B', $options[1]['label']);
        $this->assertNull($options[1]['description']);
    }

    public function testCreateGeneratesOptionIds(): void
    {
        $session = $this->createSession();

        $this->decisionRepository->method('save');
        $this->mercurePublisher->method('publishDecisionCreated');

        $decision = $this->service->create($session, 'Title', [['label' => 'A'], ['label' => 'B']]);

        $options = $decision->getOptions();
        $this->assertTrue(Uuid::isValid($options[0]['id']));
        $this->assertTrue(Uuid::isValid($options[1]['id']));
        $this->assertNotSame($options[0]['id'], $options[1]['id']);
    }

    public function testCreatePublishesMercureEvent(): void
    {
        $session = $this->createSession();

        $this->decisionRepository->method('save');

        $this->mercurePublisher->expects($this->once())
            ->method('publishDecisionCreated')
            ->with($this->isInstanceOf(Decision::class));

        $this->service->create($session, 'Title', [['label' => 'A']]);
    }

    public function testVoteCreatesNewVote(): void
    {
        $session = $this->createSession();
        $participant = $this->createParticipant($session);

        $decision = new Decision();
        $decision->setSession($session);
        $decision->setTitle('Test');
        $decision->addOption('Option A');

        $optionId = $decision->getOptions()[0]['id'];

        $this->voteRepository->method('findByDecisionAndParticipant')->willReturn(null);
        $this->voteRepository->method('save');
        $this->mercurePublisher->method('publishVoteReceived');

        $vote = $this->service->vote($decision, $participant, $optionId);

        $this->assertInstanceOf(Vote::class, $vote);
    }

    public function testVoteSetsParticipant(): void
    {
        $session = $this->createSession();
        $participant = $this->createParticipant($session);

        $decision = new Decision();
        $decision->setSession($session);
        $decision->setTitle('Test');
        $decision->addOption('Option A');

        $optionId = $decision->getOptions()[0]['id'];

        $this->voteRepository->method('findByDecisionAndParticipant')->willReturn(null);
        $this->voteRepository->method('save');
        $this->mercurePublisher->method('publishVoteReceived');

        $vote = $this->service->vote($decision, $participant, $optionId);

        $this->assertSame($participant, $vote->getParticipant());
    }

    public function testVoteSetsOptionId(): void
    {
        $session = $this->createSession();
        $participant = $this->createParticipant($session);

        $decision = new Decision();
        $decision->setSession($session);
        $decision->setTitle('Test');
        $decision->addOption('Option A');

        $optionId = $decision->getOptions()[0]['id'];

        $this->voteRepository->method('findByDecisionAndParticipant')->willReturn(null);
        $this->voteRepository->method('save');
        $this->mercurePublisher->method('publishVoteReceived');

        $vote = $this->service->vote($decision, $participant, $optionId);

        $this->assertSame($optionId, $vote->getOptionId()->toString());
    }

    public function testVoteSetsComment(): void
    {
        $session = $this->createSession();
        $participant = $this->createParticipant($session);

        $decision = new Decision();
        $decision->setSession($session);
        $decision->setTitle('Test');
        $decision->addOption('Option A');

        $optionId = $decision->getOptions()[0]['id'];

        $this->voteRepository->method('findByDecisionAndParticipant')->willReturn(null);
        $this->voteRepository->method('save');
        $this->mercurePublisher->method('publishVoteReceived');

        $vote = $this->service->vote($decision, $participant, $optionId, 'My comment');

        $this->assertSame('My comment', $vote->getComment());
    }

    public function testVoteUpdatesExistingVote(): void
    {
        $session = $this->createSession();
        $participant = $this->createParticipant($session);

        $decision = new Decision();
        $decision->setSession($session);
        $decision->setTitle('Test');
        $decision->addOption('Option A');
        $decision->addOption('Option B');

        $options = $decision->getOptions();
        $optionAId = $options[0]['id'];
        $optionBId = $options[1]['id'];

        $existingVote = new Vote();
        $existingVote->setDecision($decision);
        $existingVote->setParticipant($participant);
        $existingVote->setOptionId(Uuid::fromString($optionAId));

        $this->voteRepository->method('findByDecisionAndParticipant')->willReturn($existingVote);
        $this->entityManager->method('flush');
        $this->mercurePublisher->method('publishVoteReceived');

        $vote = $this->service->vote($decision, $participant, $optionBId);

        $this->assertSame($existingVote, $vote);
        $this->assertSame($optionBId, $vote->getOptionId()->toString());
    }

    public function testVoteThrowsWhenDecisionLocked(): void
    {
        $session = $this->createSession();
        $participant = $this->createParticipant($session);

        $decision = new Decision();
        $decision->setSession($session);
        $decision->setTitle('Test');
        $decision->addOption('Option A');
        $decision->lock();

        $optionId = $decision->getOptions()[0]['id'];

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('verrouillée');

        $this->service->vote($decision, $participant, $optionId);
    }

    public function testVotePublishesMercureEvent(): void
    {
        $session = $this->createSession();
        $participant = $this->createParticipant($session);

        $decision = new Decision();
        $decision->setSession($session);
        $decision->setTitle('Test');
        $decision->addOption('Option A');

        $optionId = $decision->getOptions()[0]['id'];

        $this->voteRepository->method('findByDecisionAndParticipant')->willReturn(null);
        $this->voteRepository->method('save');

        $this->mercurePublisher->expects($this->once())
            ->method('publishVoteReceived')
            ->with(
                $session->getId()->toString(),
                $decision->getId()->toString(),
                $this->isType('array')
            );

        $this->service->vote($decision, $participant, $optionId);
    }

    public function testRemoveVoteDeletesVote(): void
    {
        $session = $this->createSession();
        $participant = $this->createParticipant($session);

        $decision = new Decision();
        $decision->setSession($session);
        $decision->setTitle('Test');

        $this->voteRepository->expects($this->once())
            ->method('removeByDecisionAndParticipant')
            ->with($decision, $participant);
        $this->mercurePublisher->method('publishVoteReceived');

        $this->service->removeVote($decision, $participant);
    }

    public function testRemoveVoteThrowsWhenLocked(): void
    {
        $session = $this->createSession();
        $participant = $this->createParticipant($session);

        $decision = new Decision();
        $decision->setSession($session);
        $decision->setTitle('Test');
        $decision->lock();

        $this->expectException(\LogicException::class);

        $this->service->removeVote($decision, $participant);
    }

    public function testRemoveVotePublishesMercureEvent(): void
    {
        $session = $this->createSession();
        $participant = $this->createParticipant($session);

        $decision = new Decision();
        $decision->setSession($session);
        $decision->setTitle('Test');

        $this->voteRepository->method('removeByDecisionAndParticipant');

        $this->mercurePublisher->expects($this->once())
            ->method('publishVoteReceived');

        $this->service->removeVote($decision, $participant);
    }

    public function testUpdateStatusChangesStatus(): void
    {
        $session = $this->createSession();

        $decision = new Decision();
        $decision->setSession($session);
        $decision->setTitle('Test');

        $this->entityManager->method('flush');
        $this->mercurePublisher->method('publishDecisionStatusChanged');

        $result = $this->service->updateStatus($decision, Decision::STATUS_EN_DISCUSSION);

        $this->assertSame(Decision::STATUS_EN_DISCUSSION, $result->getStatus());
    }

    public function testUpdateStatusThrowsWhenLockedExceptReporte(): void
    {
        $session = $this->createSession();

        $decision = new Decision();
        $decision->setSession($session);
        $decision->setTitle('Test');
        $decision->lock();

        $this->expectException(\LogicException::class);

        $this->service->updateStatus($decision, Decision::STATUS_CONSENSUS);
    }

    public function testUpdateStatusAllowsReporteWhenLocked(): void
    {
        $session = $this->createSession();

        $decision = new Decision();
        $decision->setSession($session);
        $decision->setTitle('Test');
        $decision->lock();

        $this->entityManager->method('flush');
        $this->mercurePublisher->method('publishDecisionStatusChanged');

        $result = $this->service->updateStatus($decision, Decision::STATUS_REPORTE);

        $this->assertSame(Decision::STATUS_REPORTE, $result->getStatus());
    }

    public function testUpdateStatusPublishesMercureEvent(): void
    {
        $session = $this->createSession();

        $decision = new Decision();
        $decision->setSession($session);
        $decision->setTitle('Test');

        $this->entityManager->method('flush');

        $this->mercurePublisher->expects($this->once())
            ->method('publishDecisionStatusChanged');

        $this->service->updateStatus($decision, Decision::STATUS_CONSENSUS);
    }

    public function testValidateSetsSelectedOption(): void
    {
        $session = $this->createSession();

        $decision = new Decision();
        $decision->setSession($session);
        $decision->setTitle('Test');
        $decision->addOption('Winner');

        $optionId = $decision->getOptions()[0]['id'];

        $this->entityManager->method('flush');
        $this->mercurePublisher->method('publishDecisionStatusChanged');

        $result = $this->service->validate($decision, $optionId);

        $this->assertSame($optionId, $result->getSelectedOptionId()->toString());
    }

    public function testValidateSetsStatusToValide(): void
    {
        $session = $this->createSession();

        $decision = new Decision();
        $decision->setSession($session);
        $decision->setTitle('Test');
        $decision->addOption('Winner');

        $optionId = $decision->getOptions()[0]['id'];

        $this->entityManager->method('flush');
        $this->mercurePublisher->method('publishDecisionStatusChanged');

        $result = $this->service->validate($decision, $optionId);

        $this->assertSame(Decision::STATUS_VALIDE, $result->getStatus());
    }

    public function testValidateLocksDecision(): void
    {
        $session = $this->createSession();

        $decision = new Decision();
        $decision->setSession($session);
        $decision->setTitle('Test');
        $decision->addOption('Winner');

        $optionId = $decision->getOptions()[0]['id'];

        $this->entityManager->method('flush');
        $this->mercurePublisher->method('publishDecisionStatusChanged');

        $result = $this->service->validate($decision, $optionId);

        $this->assertTrue($result->isLocked());
    }

    public function testValidatePublishesMercureEvent(): void
    {
        $session = $this->createSession();

        $decision = new Decision();
        $decision->setSession($session);
        $decision->setTitle('Test');
        $decision->addOption('Winner');

        $optionId = $decision->getOptions()[0]['id'];

        $this->entityManager->method('flush');

        $this->mercurePublisher->expects($this->once())
            ->method('publishDecisionStatusChanged');

        $this->service->validate($decision, $optionId);
    }

    public function testPostponeSetsStatusToReporte(): void
    {
        $session = $this->createSession();

        $decision = new Decision();
        $decision->setSession($session);
        $decision->setTitle('Test');
        $decision->lock();

        $this->entityManager->method('flush');
        $this->mercurePublisher->method('publishDecisionStatusChanged');

        $result = $this->service->postpone($decision);

        $this->assertSame(Decision::STATUS_REPORTE, $result->getStatus());
    }

    public function testPostponeUnlocksDecision(): void
    {
        $session = $this->createSession();

        $decision = new Decision();
        $decision->setSession($session);
        $decision->setTitle('Test');
        $decision->lock();

        $this->assertTrue($decision->isLocked());

        $this->entityManager->method('flush');
        $this->mercurePublisher->method('publishDecisionStatusChanged');

        $result = $this->service->postpone($decision);

        $this->assertFalse($result->isLocked());
    }

    public function testPostponePublishesMercureEvent(): void
    {
        $session = $this->createSession();

        $decision = new Decision();
        $decision->setSession($session);
        $decision->setTitle('Test');

        $this->entityManager->method('flush');

        $this->mercurePublisher->expects($this->once())
            ->method('publishDecisionStatusChanged');

        $this->service->postpone($decision);
    }

    public function testDeleteRemovesVotes(): void
    {
        $session = $this->createSession();
        $participant = $this->createParticipant($session);

        $decision = new Decision();
        $decision->setSession($session);
        $decision->setTitle('Test');

        $vote = new Vote();
        $vote->setDecision($decision);
        $vote->setParticipant($participant);
        $vote->setOptionId(Uuid::v7());
        $decision->addVote($vote);

        $this->entityManager->expects($this->exactly(2))
            ->method('remove'); // 1 vote + 1 decision
        $this->entityManager->method('flush');
        $this->mercurePublisher->method('publishDecisionDeleted');

        $this->service->delete($decision);
    }

    public function testDeleteRemovesDecision(): void
    {
        $session = $this->createSession();

        $decision = new Decision();
        $decision->setSession($session);
        $decision->setTitle('Test');

        $this->entityManager->expects($this->once())
            ->method('remove')
            ->with($decision);
        $this->entityManager->method('flush');
        $this->mercurePublisher->method('publishDecisionDeleted');

        $this->service->delete($decision);
    }

    public function testDeletePublishesMercureEvent(): void
    {
        $session = $this->createSession();

        $decision = new Decision();
        $decision->setSession($session);
        $decision->setTitle('Test');

        $this->entityManager->method('remove');
        $this->entityManager->method('flush');

        $this->mercurePublisher->expects($this->once())
            ->method('publishDecisionDeleted')
            ->with(
                $session->getId()->toString(),
                $decision->getId()->toString(),
                $this->anything()
            );

        $this->service->delete($decision);
    }

    public function testSerializeIncludesAllFields(): void
    {
        $session = $this->createSession();

        $decision = new Decision();
        $decision->setSession($session);
        $decision->setTitle('Test');
        $decision->setDescription('Desc');
        $decision->addOption('A');

        $serialized = $this->service->serialize($decision);

        $this->assertArrayHasKey('id', $serialized);
        $this->assertArrayHasKey('title', $serialized);
        $this->assertArrayHasKey('description', $serialized);
        $this->assertArrayHasKey('status', $serialized);
        $this->assertArrayHasKey('options', $serialized);
        $this->assertArrayHasKey('selected_option_id', $serialized);
        $this->assertArrayHasKey('is_locked', $serialized);
        $this->assertArrayHasKey('vote_stats', $serialized);
        $this->assertArrayHasKey('vote_count', $serialized);
        $this->assertArrayHasKey('linked_document_id', $serialized);
        $this->assertArrayHasKey('created_at', $serialized);
        $this->assertArrayHasKey('updated_at', $serialized);
    }

    public function testSerializeIncludesVoteStats(): void
    {
        $session = $this->createSession();

        $decision = new Decision();
        $decision->setSession($session);
        $decision->setTitle('Test');
        $decision->addOption('A');
        $decision->addOption('B');

        $serialized = $this->service->serialize($decision);

        $this->assertIsArray($serialized['vote_stats']);
        $this->assertCount(2, $serialized['vote_stats']);
    }

    public function testSerializeIncludesVoteCount(): void
    {
        $session = $this->createSession();
        $participant = $this->createParticipant($session);

        $decision = new Decision();
        $decision->setSession($session);
        $decision->setTitle('Test');
        $decision->addOption('A');

        $vote = new Vote();
        $vote->setDecision($decision);
        $vote->setParticipant($participant);
        $vote->setOptionId(Uuid::v7());
        $decision->addVote($vote);

        $serialized = $this->service->serialize($decision);

        $this->assertSame(1, $serialized['vote_count']);
    }

    public function testVoteChangeOption(): void
    {
        $session = $this->createSession();
        $participant = $this->createParticipant($session);

        $decision = new Decision();
        $decision->setSession($session);
        $decision->setTitle('Test');
        $decision->addOption('Option A');
        $decision->addOption('Option B');

        $options = $decision->getOptions();
        $optionAId = $options[0]['id'];
        $optionBId = $options[1]['id'];

        // First vote for A
        $existingVote = new Vote();
        $existingVote->setDecision($decision);
        $existingVote->setParticipant($participant);
        $existingVote->setOptionId(Uuid::fromString($optionAId));

        $this->voteRepository->method('findByDecisionAndParticipant')->willReturn($existingVote);
        $this->entityManager->method('flush');
        $this->mercurePublisher->method('publishVoteReceived');

        // Change to B
        $vote = $this->service->vote($decision, $participant, $optionBId);

        $this->assertSame($optionBId, $vote->getOptionId()->toString());
    }

    public function testStatusTransitions(): void
    {
        $session = $this->createSession();

        $decision = new Decision();
        $decision->setSession($session);
        $decision->setTitle('Test');

        $this->entityManager->method('flush');
        $this->mercurePublisher->method('publishDecisionStatusChanged');

        // OUVERT -> EN_DISCUSSION
        $this->service->updateStatus($decision, Decision::STATUS_EN_DISCUSSION);
        $this->assertSame(Decision::STATUS_EN_DISCUSSION, $decision->getStatus());

        // EN_DISCUSSION -> CONSENSUS
        $this->service->updateStatus($decision, Decision::STATUS_CONSENSUS);
        $this->assertSame(Decision::STATUS_CONSENSUS, $decision->getStatus());
    }
}

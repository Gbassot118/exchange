<?php

namespace App\Tests\Unit\Service\Session;

use App\Entity\Participant;
use App\Entity\Session;
use App\Repository\ParticipantRepository;
use App\Repository\SessionRepository;
use App\Service\Mercure\MercurePublisher;
use App\Service\Session\SessionService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class SessionServiceTest extends TestCase
{
    private EntityManagerInterface&MockObject $entityManager;
    private SessionRepository&MockObject $sessionRepository;
    private ParticipantRepository&MockObject $participantRepository;
    private MercurePublisher&MockObject $mercurePublisher;
    private SessionService $service;

    protected function setUp(): void
    {
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->sessionRepository = $this->createMock(SessionRepository::class);
        $this->participantRepository = $this->createMock(ParticipantRepository::class);
        $this->mercurePublisher = $this->createMock(MercurePublisher::class);

        $this->service = new SessionService(
            $this->entityManager,
            $this->sessionRepository,
            $this->participantRepository,
            $this->mercurePublisher
        );
    }

    public function testCreateReturnsSession(): void
    {
        $this->sessionRepository->expects($this->once())
            ->method('save')
            ->with($this->isInstanceOf(Session::class), true);

        $session = $this->service->create('Test Session');

        $this->assertInstanceOf(Session::class, $session);
    }

    public function testCreateSetsTitle(): void
    {
        $this->sessionRepository->method('save');

        $session = $this->service->create('My Session Title');

        $this->assertSame('My Session Title', $session->getTitle());
    }

    public function testCreateSetsDescription(): void
    {
        $this->sessionRepository->method('save');

        $session = $this->service->create('Title', 'My Description');

        $this->assertSame('My Description', $session->getDescription());
    }

    public function testCreateWithNullDescription(): void
    {
        $this->sessionRepository->method('save');

        $session = $this->service->create('Title', null);

        $this->assertNull($session->getDescription());
    }

    public function testCreateCallsRepositorySave(): void
    {
        $this->sessionRepository->expects($this->once())
            ->method('save')
            ->with(
                $this->isInstanceOf(Session::class),
                $this->isTrue()
            );

        $this->service->create('Test');
    }

    public function testUpdateStatusChangesStatus(): void
    {
        $session = new Session();
        $session->setTitle('Test');

        $this->entityManager->expects($this->once())->method('flush');
        $this->mercurePublisher->expects($this->once())->method('publishSessionStatusChanged');

        $result = $this->service->updateStatus($session, Session::STATUS_EN_COURS);

        $this->assertSame(Session::STATUS_EN_COURS, $result->getStatus());
    }

    public function testUpdateStatusFlushesEntityManager(): void
    {
        $session = new Session();
        $session->setTitle('Test');

        $this->entityManager->expects($this->once())->method('flush');
        $this->mercurePublisher->method('publishSessionStatusChanged');

        $this->service->updateStatus($session, Session::STATUS_TERMINE);
    }

    public function testUpdateStatusPublishesMercureEvent(): void
    {
        $session = new Session();
        $session->setTitle('Test');

        $this->entityManager->method('flush');
        $this->mercurePublisher->expects($this->once())
            ->method('publishSessionStatusChanged')
            ->with(
                $session->getId()->toString(),
                Session::STATUS_EN_COURS
            );

        $this->service->updateStatus($session, Session::STATUS_EN_COURS);
    }

    public function testUpdateStatusReturnsSession(): void
    {
        $session = new Session();
        $session->setTitle('Test');

        $this->entityManager->method('flush');
        $this->mercurePublisher->method('publishSessionStatusChanged');

        $result = $this->service->updateStatus($session, Session::STATUS_TERMINE);

        $this->assertSame($session, $result);
    }

    public function testFindByInviteCodeReturnsSession(): void
    {
        $session = new Session();
        $session->setTitle('Test');

        $this->sessionRepository->expects($this->once())
            ->method('findByInviteCode')
            ->with('abc123')
            ->willReturn($session);

        $result = $this->service->findByInviteCode('abc123');

        $this->assertSame($session, $result);
    }

    public function testFindByInviteCodeReturnsNullWhenNotFound(): void
    {
        $this->sessionRepository->expects($this->once())
            ->method('findByInviteCode')
            ->with('invalid')
            ->willReturn(null);

        $result = $this->service->findByInviteCode('invalid');

        $this->assertNull($result);
    }

    public function testJoinSessionCreatesNewParticipant(): void
    {
        $session = new Session();
        $session->setTitle('Test');

        $this->participantRepository->method('findBySessionAndPseudo')->willReturn(null);
        $this->participantRepository->expects($this->once())
            ->method('save')
            ->with($this->isInstanceOf(Participant::class), true);
        $this->participantRepository->method('findOnlineInSession')->willReturn([]);
        $this->mercurePublisher->method('publishPresenceUpdate');

        $participant = $this->service->joinSession($session, 'NewUser');

        $this->assertInstanceOf(Participant::class, $participant);
        $this->assertSame('NewUser', $participant->getPseudo());
    }

    public function testJoinSessionSetsParticipantPseudo(): void
    {
        $session = new Session();
        $session->setTitle('Test');

        $this->participantRepository->method('findBySessionAndPseudo')->willReturn(null);
        $this->participantRepository->method('save');
        $this->participantRepository->method('findOnlineInSession')->willReturn([]);
        $this->mercurePublisher->method('publishPresenceUpdate');

        $participant = $this->service->joinSession($session, 'TestPseudo');

        $this->assertSame('TestPseudo', $participant->getPseudo());
    }

    public function testJoinSessionSetsIsAgent(): void
    {
        $session = new Session();
        $session->setTitle('Test');

        $this->participantRepository->method('findBySessionAndPseudo')->willReturn(null);
        $this->participantRepository->method('save');
        $this->participantRepository->method('findOnlineInSession')->willReturn([]);
        $this->mercurePublisher->method('publishPresenceUpdate');

        $participant = $this->service->joinSession($session, 'Agent', true);

        $this->assertTrue($participant->isAgent());
    }

    public function testJoinSessionReturnsExistingParticipantIfPseudoExists(): void
    {
        $session = new Session();
        $session->setTitle('Test');

        $existingParticipant = new Participant();
        $existingParticipant->setSession($session);
        $existingParticipant->setPseudo('ExistingUser');

        $this->participantRepository->method('findBySessionAndPseudo')
            ->with($session, 'ExistingUser')
            ->willReturn($existingParticipant);
        $this->entityManager->method('flush');

        $participant = $this->service->joinSession($session, 'ExistingUser');

        $this->assertSame($existingParticipant, $participant);
    }

    public function testJoinSessionUpdatesLastSeenAtForExisting(): void
    {
        $session = new Session();
        $session->setTitle('Test');

        $existingParticipant = new Participant();
        $existingParticipant->setSession($session);
        $existingParticipant->setPseudo('ExistingUser');
        $existingParticipant->setLastSeenAt(new \DateTimeImmutable('-1 hour'));

        $this->participantRepository->method('findBySessionAndPseudo')->willReturn($existingParticipant);
        $this->entityManager->expects($this->once())->method('flush');

        $participant = $this->service->joinSession($session, 'ExistingUser');

        $this->assertEqualsWithDelta(new \DateTimeImmutable(), $participant->getLastSeenAt(), 1);
    }

    public function testJoinSessionBroadcastsPresenceForNewParticipant(): void
    {
        $session = new Session();
        $session->setTitle('Test');

        $this->participantRepository->method('findBySessionAndPseudo')->willReturn(null);
        $this->participantRepository->method('save');
        $this->participantRepository->method('findOnlineInSession')->willReturn([]);

        $this->mercurePublisher->expects($this->once())
            ->method('publishPresenceUpdate');

        $this->service->joinSession($session, 'NewUser');
    }

    public function testJoinSessionDoesNotBroadcastForExisting(): void
    {
        $session = new Session();
        $session->setTitle('Test');

        $existingParticipant = new Participant();
        $existingParticipant->setSession($session);
        $existingParticipant->setPseudo('ExistingUser');

        $this->participantRepository->method('findBySessionAndPseudo')->willReturn($existingParticipant);
        $this->entityManager->method('flush');

        // Should NOT broadcast for existing participant
        $this->mercurePublisher->expects($this->never())
            ->method('publishPresenceUpdate');

        $this->service->joinSession($session, 'ExistingUser');
    }

    public function testUpdateParticipantPresenceSetsLastSeenAt(): void
    {
        $session = new Session();
        $session->setTitle('Test');

        $participant = new Participant();
        $participant->setSession($session);
        $participant->setPseudo('User');

        $this->entityManager->method('flush');
        $this->participantRepository->method('findOnlineInSession')->willReturn([]);
        $this->mercurePublisher->method('publishPresenceUpdate');

        $this->service->updateParticipantPresence($participant);

        $this->assertEqualsWithDelta(new \DateTimeImmutable(), $participant->getLastSeenAt(), 1);
    }

    public function testUpdateParticipantPresenceSetsCurrentDocumentId(): void
    {
        $session = new Session();
        $session->setTitle('Test');

        $participant = new Participant();
        $participant->setSession($session);
        $participant->setPseudo('User');

        $documentId = '01234567-89ab-7cde-8f01-234567890abc';

        $this->entityManager->method('flush');
        $this->participantRepository->method('findOnlineInSession')->willReturn([]);
        $this->mercurePublisher->method('publishPresenceUpdate');

        $this->service->updateParticipantPresence($participant, $documentId);

        $this->assertSame($documentId, $participant->getCurrentDocumentId()->toString());
    }

    public function testUpdateParticipantPresenceWithNullDocumentId(): void
    {
        $session = new Session();
        $session->setTitle('Test');

        $participant = new Participant();
        $participant->setSession($session);
        $participant->setPseudo('User');

        $this->entityManager->method('flush');
        $this->participantRepository->method('findOnlineInSession')->willReturn([]);
        $this->mercurePublisher->method('publishPresenceUpdate');

        $this->service->updateParticipantPresence($participant, null);

        $this->assertNull($participant->getCurrentDocumentId());
    }

    public function testUpdateParticipantPresenceBroadcasts(): void
    {
        $session = new Session();
        $session->setTitle('Test');

        $participant = new Participant();
        $participant->setSession($session);
        $participant->setPseudo('User');

        $this->entityManager->method('flush');
        $this->participantRepository->method('findOnlineInSession')->willReturn([]);
        $this->mercurePublisher->expects($this->once())->method('publishPresenceUpdate');

        $this->service->updateParticipantPresence($participant);
    }

    public function testGetOnlineParticipantsReturnsArray(): void
    {
        $session = new Session();
        $session->setTitle('Test');

        $participants = [new Participant(), new Participant()];

        $this->participantRepository->expects($this->once())
            ->method('findOnlineInSession')
            ->willReturn($participants);

        $result = $this->service->getOnlineParticipants($session);

        $this->assertIsArray($result);
        $this->assertCount(2, $result);
    }

    public function testBroadcastPresenceCallsMercure(): void
    {
        $session = new Session();
        $session->setTitle('Test');

        $this->participantRepository->method('findOnlineInSession')->willReturn([]);
        $this->mercurePublisher->expects($this->once())
            ->method('publishPresenceUpdate')
            ->with($session->getId()->toString(), $this->isType('array'));

        $this->service->broadcastPresence($session);
    }

    public function testBroadcastPresenceIncludesAllParticipantData(): void
    {
        $session = new Session();
        $session->setTitle('Test');

        $participant = new Participant();
        $participant->setSession($session);
        $participant->setPseudo('TestUser');

        $this->participantRepository->method('findOnlineInSession')->willReturn([$participant]);

        $this->mercurePublisher->expects($this->once())
            ->method('publishPresenceUpdate')
            ->with(
                $session->getId()->toString(),
                $this->callback(function (array $data) {
                    return count($data) === 1
                        && isset($data[0]['id'])
                        && isset($data[0]['pseudo'])
                        && isset($data[0]['color'])
                        && array_key_exists('current_document_id', $data[0])
                        && isset($data[0]['is_agent']);
                })
            );

        $this->service->broadcastPresence($session);
    }

    public function testArchiveSetsStatusToArchive(): void
    {
        $session = new Session();
        $session->setTitle('Test');

        $this->entityManager->method('flush');
        $this->mercurePublisher->method('publishSessionStatusChanged');

        $result = $this->service->archive($session);

        $this->assertSame(Session::STATUS_ARCHIVE, $result->getStatus());
    }

    public function testArchiveReturnsSession(): void
    {
        $session = new Session();
        $session->setTitle('Test');

        $this->entityManager->method('flush');
        $this->mercurePublisher->method('publishSessionStatusChanged');

        $result = $this->service->archive($session);

        $this->assertSame($session, $result);
    }

    public function testRegenerateInviteCodeChangesCode(): void
    {
        $session = new Session();
        $session->setTitle('Test');
        $originalCode = $session->getInviteCode();

        $this->entityManager->expects($this->once())->method('flush');

        $result = $this->service->regenerateInviteCode($session);

        $this->assertNotSame($originalCode, $result->getInviteCode());
    }

    public function testRegenerateInviteCodeFlushes(): void
    {
        $session = new Session();
        $session->setTitle('Test');

        $this->entityManager->expects($this->once())->method('flush');

        $this->service->regenerateInviteCode($session);
    }

    public function testRegenerateInviteCodeReturnsSession(): void
    {
        $session = new Session();
        $session->setTitle('Test');

        $this->entityManager->method('flush');

        $result = $this->service->regenerateInviteCode($session);

        $this->assertSame($session, $result);
    }

    public function testJoinSessionWithAgentFlag(): void
    {
        $session = new Session();
        $session->setTitle('Test');

        $this->participantRepository->method('findBySessionAndPseudo')->willReturn(null);
        $this->participantRepository->method('save');
        $this->participantRepository->method('findOnlineInSession')->willReturn([]);
        $this->mercurePublisher->method('publishPresenceUpdate');

        $participant = $this->service->joinSession($session, 'AIAgent', true);

        $this->assertTrue($participant->isAgent());
    }

    public function testJoinSessionPreservesExistingParticipantColor(): void
    {
        $session = new Session();
        $session->setTitle('Test');

        $existingParticipant = new Participant();
        $existingParticipant->setSession($session);
        $existingParticipant->setPseudo('User');
        $existingParticipant->setColor('#FF0000');

        $this->participantRepository->method('findBySessionAndPseudo')->willReturn($existingParticipant);
        $this->entityManager->method('flush');

        $participant = $this->service->joinSession($session, 'User');

        $this->assertSame('#FF0000', $participant->getColor());
    }

    public function testMultipleParticipantsCanJoin(): void
    {
        $session = new Session();
        $session->setTitle('Test');

        $this->participantRepository->method('findBySessionAndPseudo')->willReturn(null);
        $this->participantRepository->method('save');
        $this->participantRepository->method('findOnlineInSession')->willReturn([]);
        $this->mercurePublisher->method('publishPresenceUpdate');

        $participant1 = $this->service->joinSession($session, 'User1');
        $participant2 = $this->service->joinSession($session, 'User2');
        $participant3 = $this->service->joinSession($session, 'User3');

        $this->assertSame('User1', $participant1->getPseudo());
        $this->assertSame('User2', $participant2->getPseudo());
        $this->assertSame('User3', $participant3->getPseudo());
    }
}

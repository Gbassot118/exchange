<?php

namespace App\Service\Session;

use App\Entity\Participant;
use App\Entity\Session;
use App\Repository\ParticipantRepository;
use App\Repository\SessionRepository;
use App\Service\Mercure\MercurePublisher;
use Doctrine\ORM\EntityManagerInterface;

final class SessionService
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly SessionRepository $sessionRepository,
        private readonly ParticipantRepository $participantRepository,
        private readonly MercurePublisher $mercurePublisher,
    ) {}

    public function create(string $title, ?string $description = null): Session
    {
        $session = new Session();
        $session->setTitle($title);
        $session->setDescription($description);

        $this->sessionRepository->save($session, true);

        return $session;
    }

    public function updateStatus(Session $session, string $status): Session
    {
        $session->setStatus($status);
        $this->entityManager->flush();

        $this->mercurePublisher->publishSessionStatusChanged(
            $session->getId()->toString(),
            $status
        );

        return $session;
    }

    public function findByInviteCode(string $inviteCode): ?Session
    {
        return $this->sessionRepository->findByInviteCode($inviteCode);
    }

    public function joinSession(Session $session, string $pseudo, bool $isAgent = false): Participant
    {
        $existingParticipant = $this->participantRepository->findBySessionAndPseudo($session, $pseudo);
        if ($existingParticipant !== null) {
            $existingParticipant->setLastSeenAt(new \DateTimeImmutable());
            $this->entityManager->flush();
            return $existingParticipant;
        }

        $participant = new Participant();
        $participant->setPseudo($pseudo);
        $participant->setSession($session);
        $participant->setIsAgent($isAgent);
        $participant->setLastSeenAt(new \DateTimeImmutable());

        $this->participantRepository->save($participant, true);

        $this->broadcastPresence($session);

        return $participant;
    }

    public function updateParticipantPresence(Participant $participant, ?string $currentDocumentId = null): void
    {
        $participant->setLastSeenAt(new \DateTimeImmutable());
        if ($currentDocumentId !== null) {
            $participant->setCurrentDocumentId(\Symfony\Component\Uid\Uuid::fromString($currentDocumentId));
        }
        $this->entityManager->flush();

        $this->broadcastPresence($participant->getSession());
    }

    public function getOnlineParticipants(Session $session): array
    {
        $threshold = new \DateTimeImmutable('-30 seconds');
        return $this->participantRepository->findOnlineInSession(
            $session->getId()->toString(),
            $threshold
        );
    }

    public function broadcastPresence(Session $session): void
    {
        $participants = $this->getOnlineParticipants($session);

        $this->mercurePublisher->publishPresenceUpdate(
            $session->getId()->toString(),
            array_map(fn(Participant $p) => [
                'id' => $p->getId()->toString(),
                'pseudo' => $p->getPseudo(),
                'color' => $p->getColor(),
                'current_document_id' => $p->getCurrentDocumentId()?->toString(),
                'is_agent' => $p->isAgent(),
            ], $participants)
        );
    }

    public function archive(Session $session): Session
    {
        return $this->updateStatus($session, Session::STATUS_ARCHIVE);
    }

    public function regenerateInviteCode(Session $session): Session
    {
        $session->setInviteCode(bin2hex(random_bytes(16)));
        $this->entityManager->flush();
        return $session;
    }
}

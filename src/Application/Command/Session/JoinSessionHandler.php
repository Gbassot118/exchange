<?php

declare(strict_types=1);

namespace App\Application\Command\Session;

use App\Application\DTO\Response\ParticipantResponse;
use App\Application\DTO\Response\SessionResponse;
use App\Application\DTO\Transformer\SessionTransformer;
use App\Application\Port\EventPublisherInterface;
use App\Application\Port\RealtimeNotifierInterface;
use App\Domain\Session\Event\ParticipantJoined;
use App\Domain\Session\Exception\InviteCodeExpiredException;
use App\Domain\Session\Exception\SessionArchivedException;
use App\Domain\Session\Exception\SessionNotFoundException;
use App\Domain\Session\Repository\ParticipantRepositoryInterface;
use App\Domain\Session\Repository\SessionRepositoryInterface;
use App\Domain\Session\ValueObject\ParticipantColor;
use App\Entity\Participant;

use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class JoinSessionHandler
{
    public function __construct(
        private SessionRepositoryInterface $sessionRepository,
        private ParticipantRepositoryInterface $participantRepository,
        private SessionTransformer $sessionTransformer,
        private EventPublisherInterface $eventPublisher,
        private RealtimeNotifierInterface $realtimeNotifier,
    ) {}

    /**
     * @return array{session: SessionResponse, participant: ParticipantResponse}
     */
    public function __invoke(JoinSessionCommand $command): array
    {
        $session = $this->sessionRepository->findByInviteCode($command->inviteCode);

        if ($session === null) {
            throw SessionNotFoundException::withInviteCode($command->inviteCode);
        }

        if ($session->getStatus() === 'archive') {
            throw SessionArchivedException::create($session->getId()->toString());
        }

        // Check if invite code has expired
        if ($session->isInviteCodeExpired()) {
            throw InviteCodeExpiredException::create($session->getId()->toString());
        }

        // Check if participant already exists (reconnection)
        $existingParticipant = $this->participantRepository->findBySessionAndPseudo($session, $command->pseudo);
        if ($existingParticipant !== null) {
            // Update last seen and return existing participant
            $existingParticipant->setLastSeenAt(new \DateTimeImmutable());
            $this->participantRepository->save($existingParticipant);

            return [
                'session' => $this->sessionTransformer->toResponse($session),
                'participant' => $this->sessionTransformer->toParticipantResponse($existingParticipant),
            ];
        }

        $color = $command->color ?? ParticipantColor::random()->value();

        $participant = new Participant();
        $participant->setSession($session);
        $participant->setPseudo($command->pseudo);
        $participant->setColor($color);
        $participant->setIsAgent($command->isAgent);

        $this->participantRepository->save($participant);

        $event = new ParticipantJoined(
            sessionId: $session->getId()->toString(),
            participantId: $participant->getId()->toString(),
            pseudo: $participant->getPseudo(),
            isAgent: $participant->isAgent(),
        );

        $this->eventPublisher->publish($event);

        $this->realtimeNotifier->notifyParticipantJoined(
            sessionId: $session->getId()->toString(),
            participant: $this->sessionTransformer->toParticipantResponse($participant),
        );

        return [
            'session' => $this->sessionTransformer->toResponse($session),
            'participant' => $this->sessionTransformer->toParticipantResponse($participant),
        ];
    }
}

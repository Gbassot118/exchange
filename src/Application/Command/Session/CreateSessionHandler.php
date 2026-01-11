<?php

declare(strict_types=1);

namespace App\Application\Command\Session;

use App\Application\DTO\Response\SessionResponse;
use App\Application\DTO\Transformer\SessionTransformer;
use App\Application\Port\EventPublisherInterface;
use App\Domain\Session\Event\ParticipantJoined;
use App\Domain\Session\Event\SessionCreated;
use App\Domain\Session\Repository\ParticipantRepositoryInterface;
use App\Domain\Session\Repository\SessionRepositoryInterface;
use App\Domain\Session\ValueObject\InviteCode;
use App\Domain\Session\ValueObject\ParticipantColor;
use App\Entity\Participant;
use App\Entity\Session;

use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class CreateSessionHandler
{
    public function __construct(
        private SessionRepositoryInterface $sessionRepository,
        private ParticipantRepositoryInterface $participantRepository,
        private SessionTransformer $sessionTransformer,
        private EventPublisherInterface $eventPublisher,
    ) {}

    /**
     * @return array{session: SessionResponse, participant: \App\Application\DTO\Response\ParticipantResponse}
     */
    public function __invoke(CreateSessionCommand $command): array
    {
        $session = new Session();
        $session->setTitle($command->title);
        $session->setDescription($command->description);
        $session->setInviteCode(InviteCode::generate()->value());

        $this->sessionRepository->save($session);

        $participant = new Participant();
        $participant->setSession($session);
        $participant->setPseudo($command->creatorPseudo);
        $participant->setColor(ParticipantColor::random()->value());
        $participant->setIsAgent($command->isAgent);

        $this->participantRepository->save($participant);

        $this->eventPublisher->publish(new SessionCreated(
            sessionId: $session->getId()->toString(),
            title: $session->getTitle(),
            creatorPseudo: $participant->getPseudo(),
        ));

        $this->eventPublisher->publish(new ParticipantJoined(
            sessionId: $session->getId()->toString(),
            participantId: $participant->getId()->toString(),
            pseudo: $participant->getPseudo(),
            isAgent: $participant->isAgent(),
        ));

        return [
            'session' => $this->sessionTransformer->toResponse($session),
            'participant' => $this->sessionTransformer->toParticipantResponse($participant),
        ];
    }
}

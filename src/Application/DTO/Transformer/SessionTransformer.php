<?php

declare(strict_types=1);

namespace App\Application\DTO\Transformer;

use App\Application\DTO\Response\ParticipantResponse;
use App\Application\DTO\Response\SessionResponse;
use App\Application\DTO\Response\SessionStatistics;
use App\Application\DTO\Response\SessionStatusResponse;
use App\Entity\Participant;
use App\Entity\Session;

final class SessionTransformer
{
    public function toResponse(Session $session, bool $includeParticipants = true): SessionResponse
    {
        return SessionResponse::fromEntity($session, $includeParticipants);
    }

    public function toParticipantResponse(Participant $participant): ParticipantResponse
    {
        return ParticipantResponse::fromEntity($participant);
    }

    /**
     * @param array<\App\Application\DTO\Response\DecisionResponse> $decisions
     * @param array<\App\Application\DTO\Response\AnnotationResponse> $priorityAnnotations
     */
    public function toStatusResponse(
        Session $session,
        int $totalDocuments,
        int $openAnnotations,
        int $untreatedAnnotations,
        int $pendingDecisions,
        int $onlineParticipants,
        array $decisions,
        array $priorityAnnotations,
    ): SessionStatusResponse {
        return new SessionStatusResponse(
            session: $this->toResponse($session, includeParticipants: false),
            statistics: new SessionStatistics(
                totalDocuments: $totalDocuments,
                openAnnotations: $openAnnotations,
                untreatedAnnotations: $untreatedAnnotations,
                pendingDecisions: $pendingDecisions,
                onlineParticipants: $onlineParticipants,
            ),
            decisions: $decisions,
            priorityAnnotations: $priorityAnnotations,
        );
    }

    /**
     * @param iterable<Session> $sessions
     * @return array<SessionResponse>
     */
    public function toResponseList(iterable $sessions, bool $includeParticipants = false): array
    {
        $responses = [];
        foreach ($sessions as $session) {
            $responses[] = $this->toResponse($session, $includeParticipants);
        }
        return $responses;
    }

    /**
     * @param iterable<Participant> $participants
     * @return array<ParticipantResponse>
     */
    public function toParticipantResponseList(iterable $participants): array
    {
        $responses = [];
        foreach ($participants as $participant) {
            $responses[] = $this->toParticipantResponse($participant);
        }
        return $responses;
    }
}

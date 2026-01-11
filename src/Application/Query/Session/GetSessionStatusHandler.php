<?php

declare(strict_types=1);

namespace App\Application\Query\Session;

use App\Application\DTO\Response\SessionStatusResponse;
use App\Application\DTO\Transformer\AnnotationTransformer;
use App\Application\DTO\Transformer\DecisionTransformer;
use App\Application\DTO\Transformer\SessionTransformer;
use App\Domain\Collaboration\Repository\AnnotationRepositoryInterface;
use App\Domain\Decision\Repository\DecisionRepositoryInterface;
use App\Domain\Session\Exception\SessionNotFoundException;
use App\Domain\Session\Repository\ParticipantRepositoryInterface;
use App\Domain\Session\Repository\SessionRepositoryInterface;
use App\Domain\Session\ValueObject\SessionId;

final readonly class GetSessionStatusHandler
{
    public function __construct(
        private SessionRepositoryInterface $sessionRepository,
        private ParticipantRepositoryInterface $participantRepository,
        private AnnotationRepositoryInterface $annotationRepository,
        private DecisionRepositoryInterface $decisionRepository,
        private SessionTransformer $sessionTransformer,
        private AnnotationTransformer $annotationTransformer,
        private DecisionTransformer $decisionTransformer,
    ) {}

    public function __invoke(GetSessionStatusQuery $query): SessionStatusResponse
    {
        $sessionId = SessionId::fromString($query->sessionId);
        $session = $this->sessionRepository->findById($sessionId);

        if ($session === null) {
            throw SessionNotFoundException::withId($query->sessionId);
        }

        $totalDocuments = $session->getDocuments()->count();
        $openAnnotations = $this->annotationRepository->countBySessionAndStatus($session, 'open');
        $untreatedAnnotations = $this->annotationRepository->countUntreatedBySession($session);
        $pendingDecisions = $this->decisionRepository->countPendingBySession($session);

        $threshold = new \DateTimeImmutable('-30 seconds');
        $onlineParticipants = count($this->participantRepository->findOnlineInSession($session, $threshold));

        $decisions = $this->decisionRepository->findPendingBySession($session);
        $priorityAnnotations = $this->annotationRepository->findPriorityAnnotations($session, 5);

        return $this->sessionTransformer->toStatusResponse(
            session: $session,
            totalDocuments: $totalDocuments,
            openAnnotations: $openAnnotations,
            untreatedAnnotations: $untreatedAnnotations,
            pendingDecisions: $pendingDecisions,
            onlineParticipants: $onlineParticipants,
            decisions: $this->decisionTransformer->toResponseList($decisions),
            priorityAnnotations: $this->annotationTransformer->toResponseList($priorityAnnotations, true),
        );
    }
}

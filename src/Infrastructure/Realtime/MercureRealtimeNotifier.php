<?php

declare(strict_types=1);

namespace App\Infrastructure\Realtime;

use App\Application\DTO\Response\AnnotationResponse;
use App\Application\DTO\Response\DecisionResponse;
use App\Application\DTO\Response\DocumentResponse;
use App\Application\DTO\Response\EstimationResponse;
use App\Application\DTO\Response\ParticipantResponse;
use App\Application\Port\RealtimeNotifierInterface;
use App\Service\Mercure\MercurePublisher;

final readonly class MercureRealtimeNotifier implements RealtimeNotifierInterface
{
    public function __construct(
        private MercurePublisher $mercurePublisher,
    ) {}

    public function notifySessionStatusChanged(string $sessionId, string $newStatus): void
    {
        $this->mercurePublisher->publishSessionStatusChanged($sessionId, $newStatus);
    }

    public function notifyParticipantJoined(string $sessionId, ParticipantResponse $participant): void
    {
        // Trigger a presence update - the frontend will refresh the participant list
        // For now, we'll use a simple approach that works with the existing presence system
        $this->mercurePublisher->publishPresenceUpdate($sessionId, [$participant->toArray()]);
    }

    public function notifyDocumentCreated(string $sessionId, DocumentResponse $document): void
    {
        $this->mercurePublisher->publishDocumentCreated($sessionId, $document->toArray());
    }

    public function notifyDocumentUpdated(string $sessionId, DocumentResponse $document): void
    {
        $docArray = $document->toArray();
        $this->mercurePublisher->publishDocumentUpdated(
            $sessionId,
            $docArray['id'],
            $docArray
        );
    }

    public function notifyDocumentDeleted(string $sessionId, string $documentId): void
    {
        $this->mercurePublisher->publishDocumentDeleted($sessionId, $documentId);
    }

    public function notifyAnnotationCreated(string $sessionId, AnnotationResponse $annotation): void
    {
        $annotationArray = $annotation->toArray();
        $this->mercurePublisher->publishAnnotationCreated(
            $sessionId,
            $annotationArray['document_id'],
            $annotationArray
        );
    }

    public function notifyAnnotationUpdated(string $sessionId, AnnotationResponse $annotation): void
    {
        $annotationArray = $annotation->toArray();
        $this->mercurePublisher->publishAnnotationUpdated(
            $sessionId,
            $annotationArray['document_id'],
            $annotationArray
        );
    }

    public function notifyAnnotationResolved(string $sessionId, AnnotationResponse $annotation): void
    {
        $annotationArray = $annotation->toArray();
        $this->mercurePublisher->publishAnnotationResolved(
            $sessionId,
            $annotationArray['document_id'],
            $annotationArray
        );
    }

    public function notifyAnnotationDeleted(string $sessionId, string $annotationId): void
    {
        $this->mercurePublisher->publishAnnotationDeleted($sessionId, $annotationId);
    }

    public function notifyDecisionCreated(string $sessionId, DecisionResponse $decision): void
    {
        $decisionArray = $decision->toArray();
        // Use publishToTopics indirectly through a new method or adapt existing
        $this->mercurePublisher->publishDecisionCreatedFromResponse($sessionId, $decisionArray);
    }

    public function notifyDecisionUpdated(string $sessionId, DecisionResponse $decision): void
    {
        $decisionArray = $decision->toArray();
        $this->mercurePublisher->publishDecisionUpdatedFromResponse($sessionId, $decisionArray);
    }

    public function notifyDecisionValidated(string $sessionId, DecisionResponse $decision): void
    {
        $this->mercurePublisher->publishDecisionStatusChanged($sessionId, $decision->toArray());
    }

    public function notifyVoteCast(string $sessionId, DecisionResponse $decision): void
    {
        $decisionArray = $decision->toArray();
        $this->mercurePublisher->publishVoteReceived(
            $sessionId,
            $decisionArray['id'],
            $decisionArray['vote_stats']
        );
    }

    // Estimation (Chiffrage) notifications

    public function notifyEstimationCreated(string $sessionId, EstimationResponse $estimation): void
    {
        $this->mercurePublisher->publishEstimationCreated($sessionId, $estimation->toArray());
    }

    public function notifyEstimationVoted(string $sessionId, string $estimationId, string $participantId, int $voteCount): void
    {
        $this->mercurePublisher->publishEstimationVoted($sessionId, $estimationId, $participantId, $voteCount);
    }

    public function notifyEstimationRevealed(string $sessionId, EstimationResponse $estimation): void
    {
        $this->mercurePublisher->publishEstimationRevealed($sessionId, $estimation->toArray());
    }
}

<?php

namespace App\Service\Mercure;

use App\Entity\Decision;
use Symfony\Component\Mercure\HubInterface;
use Symfony\Component\Mercure\Update;

class MercurePublisher
{
    public function __construct(
        private readonly HubInterface $hub,
    ) {}

    public function publishDocumentCreated(string $sessionId, array $document): void
    {
        $this->publishToTopics(
            ["/sessions/{$sessionId}/documents"],
            'document.created',
            $document
        );
    }

    public function publishDocumentUpdated(string $sessionId, string $documentId, array $document): void
    {
        $this->publishToTopics(
            [
                "/sessions/{$sessionId}/documents",
                "/sessions/{$sessionId}/documents/{$documentId}"
            ],
            'document.updated',
            $document
        );
    }

    public function publishDocumentDeleted(string $sessionId, string $documentId): void
    {
        $this->publishToTopics(
            ["/sessions/{$sessionId}/documents"],
            'document.deleted',
            ['id' => $documentId]
        );
    }

    public function publishAnnotationCreated(string $sessionId, string $documentId, array $annotation): void
    {
        $this->publishToTopics(
            [
                "/sessions/{$sessionId}/annotations",
                "/sessions/{$sessionId}/documents/{$documentId}"
            ],
            'annotation.created',
            $annotation
        );
    }

    public function publishAnnotationUpdated(string $sessionId, string $documentId, array $annotation): void
    {
        $this->publishToTopics(
            ["/sessions/{$sessionId}/annotations"],
            'annotation.updated',
            $annotation
        );
    }

    public function publishAnnotationResolved(string $sessionId, string $documentId, array $annotation): void
    {
        $this->publishToTopics(
            [
                "/sessions/{$sessionId}/annotations",
                "/sessions/{$sessionId}/documents/{$documentId}"
            ],
            'annotation.resolved',
            $annotation
        );
    }

    public function publishVoteReceived(string $sessionId, string $decisionId, array $voteStats): void
    {
        $this->publishToTopics(
            ["/sessions/{$sessionId}/decisions"],
            'vote.received',
            ['decision_id' => $decisionId, 'stats' => $voteStats]
        );
    }

    public function publishDecisionStatusChanged(string $sessionId, array $decision): void
    {
        $this->publishToTopics(
            ["/sessions/{$sessionId}/decisions"],
            'decision.status_changed',
            $decision
        );
    }

    public function publishDecisionCreated(Decision $decision): void
    {
        $sessionId = $decision->getSession()->getId()->toString();
        $this->publishToTopics(
            ["/sessions/{$sessionId}", "/sessions/{$sessionId}/decisions"],
            'decision.created',
            [
                'id' => $decision->getId()->toString(),
                'title' => $decision->getTitle(),
                'document_id' => $decision->getLinkedDocument()?->getId()->toString(),
            ]
        );
    }

    /**
     * Publish decision created event from a response array.
     *
     * @param array<string, mixed> $decisionData
     */
    public function publishDecisionCreatedFromResponse(string $sessionId, array $decisionData): void
    {
        $this->publishToTopics(
            ["/sessions/{$sessionId}", "/sessions/{$sessionId}/decisions"],
            'decision.created',
            [
                'id' => $decisionData['id'],
                'title' => $decisionData['title'],
                'document_id' => $decisionData['linked_document_id'] ?? null,
            ]
        );
    }

    public function publishDecisionDeleted(string $sessionId, string $decisionId, ?string $documentId): void
    {
        $this->publishToTopics(
            ["/sessions/{$sessionId}", "/sessions/{$sessionId}/decisions"],
            'decision.deleted',
            [
                'id' => $decisionId,
                'document_id' => $documentId,
            ]
        );
    }

    public function publishDecisionUpdated(Decision $decision): void
    {
        $sessionId = $decision->getSession()->getId()->toString();
        $this->publishToTopics(
            ["/sessions/{$sessionId}", "/sessions/{$sessionId}/decisions"],
            'decision.updated',
            [
                'id' => $decision->getId()->toString(),
                'title' => $decision->getTitle(),
                'status' => $decision->getStatus(),
                'vote_stats' => $decision->getVoteStats(),
                'document_id' => $decision->getLinkedDocument()?->getId()->toString(),
            ]
        );
    }

    /**
     * Publish decision updated event from a response array.
     *
     * @param array<string, mixed> $decisionData
     */
    public function publishDecisionUpdatedFromResponse(string $sessionId, array $decisionData): void
    {
        $this->publishToTopics(
            ["/sessions/{$sessionId}", "/sessions/{$sessionId}/decisions"],
            'decision.updated',
            [
                'id' => $decisionData['id'],
                'title' => $decisionData['title'],
                'status' => $decisionData['status'],
                'vote_stats' => $decisionData['vote_stats'],
                'document_id' => $decisionData['linked_document_id'] ?? null,
            ]
        );
    }

    public function publishPresenceUpdate(string $sessionId, array $participants): void
    {
        $this->publishToTopics(
            ["/sessions/{$sessionId}/presence"],
            'presence.update',
            ['participants' => $participants]
        );
    }

    public function publishUserFollowing(string $sessionId, string $participantId, ?string $targetDocumentId): void
    {
        $this->publishToTopics(
            ["/sessions/{$sessionId}/presence"],
            'presence.following',
            ['participant_id' => $participantId, 'document_id' => $targetDocumentId]
        );
    }

    public function publishSessionStatusChanged(string $sessionId, string $status): void
    {
        $this->publishToTopics(
            ["/sessions/{$sessionId}"],
            'session.status_changed',
            ['status' => $status]
        );
    }

    // ============================================
    // Estimation (Chiffrage) Methods
    // ============================================

    /**
     * @param array<string, mixed> $estimation
     */
    public function publishEstimationCreated(string $sessionId, array $estimation): void
    {
        $this->publishToTopics(
            ["/sessions/{$sessionId}", "/sessions/{$sessionId}/estimations"],
            'estimation.created',
            [
                'id' => $estimation['id'],
                'title' => $estimation['title'],
                'document_id' => $estimation['linked_document_id'] ?? null,
            ]
        );
    }

    public function publishEstimationVoted(string $sessionId, string $estimationId, string $participantId, int $voteCount): void
    {
        $this->publishToTopics(
            ["/sessions/{$sessionId}/estimations"],
            'estimation.voted',
            [
                'estimation_id' => $estimationId,
                'participant_id' => $participantId,
                'vote_count' => $voteCount,
            ]
        );
    }

    /**
     * @param array<string, mixed> $estimation
     */
    public function publishEstimationRevealed(string $sessionId, array $estimation): void
    {
        $this->publishToTopics(
            ["/sessions/{$sessionId}", "/sessions/{$sessionId}/estimations"],
            'estimation.revealed',
            $estimation
        );
    }

    /**
     * Generic publish method for use by RealtimeNotifier.
     *
     * @param array<string, mixed> $data
     */
    public function publish(string $topic, array $data): void
    {
        $type = $data['type'] ?? 'message';
        $this->publishToTopics([$topic], $type, $data);
    }

    /**
     * @param array<string, mixed> $data
     */
    private function publishToTopics(array $topics, string $type, array $data): void
    {
        $update = new Update(
            $topics,
            json_encode([
                'type' => $type,
                'data' => $data,
                'timestamp' => (new \DateTimeImmutable())->format(\DateTimeInterface::ATOM),
            ], JSON_THROW_ON_ERROR),
            private: false,
            id: null,
            type: $type  // SSE event name for htmx-ext-sse
        );

        $this->hub->publish($update);
    }
}

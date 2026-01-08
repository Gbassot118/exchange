<?php

namespace App\Service\Mercure;

use App\Entity\Decision;
use Symfony\Component\Mercure\HubInterface;
use Symfony\Component\Mercure\Update;

final class MercurePublisher
{
    public function __construct(
        private readonly HubInterface $hub,
    ) {}

    public function publishDocumentCreated(string $sessionId, array $document): void
    {
        $this->publish(
            ["/sessions/{$sessionId}/documents"],
            'document.created',
            $document
        );
    }

    public function publishDocumentUpdated(string $sessionId, string $documentId, array $document): void
    {
        $this->publish(
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
        $this->publish(
            ["/sessions/{$sessionId}/documents"],
            'document.deleted',
            ['id' => $documentId]
        );
    }

    public function publishAnnotationCreated(string $sessionId, string $documentId, array $annotation): void
    {
        $this->publish(
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
        $this->publish(
            ["/sessions/{$sessionId}/annotations"],
            'annotation.updated',
            $annotation
        );
    }

    public function publishAnnotationResolved(string $sessionId, string $documentId, array $annotation): void
    {
        $this->publish(
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
        $this->publish(
            ["/sessions/{$sessionId}/decisions"],
            'vote.received',
            ['decision_id' => $decisionId, 'stats' => $voteStats]
        );
    }

    public function publishDecisionStatusChanged(string $sessionId, array $decision): void
    {
        $this->publish(
            ["/sessions/{$sessionId}/decisions"],
            'decision.status_changed',
            $decision
        );
    }

    public function publishDecisionCreated(Decision $decision): void
    {
        $sessionId = $decision->getSession()->getId()->toString();
        $this->publish(
            ["/sessions/{$sessionId}", "/sessions/{$sessionId}/decisions"],
            'decision.created',
            [
                'id' => $decision->getId()->toString(),
                'title' => $decision->getTitle(),
                'document_id' => $decision->getLinkedDocument()?->getId()->toString(),
            ]
        );
    }

    public function publishDecisionDeleted(string $sessionId, string $decisionId, ?string $documentId): void
    {
        $this->publish(
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
        $this->publish(
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

    public function publishPresenceUpdate(string $sessionId, array $participants): void
    {
        $this->publish(
            ["/sessions/{$sessionId}/presence"],
            'presence.update',
            ['participants' => $participants]
        );
    }

    public function publishUserFollowing(string $sessionId, string $participantId, ?string $targetDocumentId): void
    {
        $this->publish(
            ["/sessions/{$sessionId}/presence"],
            'presence.following',
            ['participant_id' => $participantId, 'document_id' => $targetDocumentId]
        );
    }

    public function publishSessionStatusChanged(string $sessionId, string $status): void
    {
        $this->publish(
            ["/sessions/{$sessionId}"],
            'session.status_changed',
            ['status' => $status]
        );
    }

    private function publish(array $topics, string $type, array $data): void
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

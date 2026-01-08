<?php

namespace App\Service\Mcp;

use App\Entity\Annotation;
use App\Entity\Document;
use App\Entity\Participant;
use App\Entity\Session;
use App\Repository\AnnotationRepository;
use App\Repository\DecisionRepository;
use App\Repository\DocumentRepository;
use App\Repository\ParticipantRepository;
use App\Repository\SessionRepository;
use App\Service\Annotation\AnnotationService;
use App\Service\Decision\DecisionService;
use App\Service\Document\DocumentService;
use Symfony\Component\Uid\Uuid;

final class McpService
{
    public function __construct(
        private readonly SessionRepository $sessionRepository,
        private readonly DocumentRepository $documentRepository,
        private readonly AnnotationRepository $annotationRepository,
        private readonly DecisionRepository $decisionRepository,
        private readonly ParticipantRepository $participantRepository,
        private readonly DocumentService $documentService,
        private readonly AnnotationService $annotationService,
        private readonly DecisionService $decisionService,
    ) {}

    public function listDocuments(string $sessionId, ?string $parentId = null, ?string $type = null): array
    {
        $session = $this->getSession($sessionId);

        $documents = $this->documentRepository->findBySession(
            $session,
            $parentId ? Uuid::fromString($parentId) : null,
            $type
        );

        return array_map(
            fn(Document $doc) => $this->documentService->serialize($doc, false),
            $documents
        );
    }

    public function readDocument(string $documentId, bool $includeAnnotations = false, bool $includeVersions = false): array
    {
        $document = $this->getDocument($documentId);

        $data = $this->documentService->serialize($document, true);

        if ($includeAnnotations) {
            $annotations = $this->annotationRepository->findByDocumentWithFilters($document);
            $data['annotations'] = array_map(
                fn(Annotation $a) => $this->annotationService->serialize($a),
                $annotations
            );
        }

        if ($includeVersions) {
            $data['versions'] = array_map(
                fn($v) => [
                    'version' => $v->getVersion(),
                    'author' => $v->getAuthor()?->getPseudo(),
                    'change_description' => $v->getChangeDescription(),
                    'created_at' => $v->getCreatedAt()->format(\DateTimeInterface::ATOM),
                ],
                $document->getVersions()->toArray()
            );
        }

        return $data;
    }

    public function createDocument(string $sessionId, array $data, ?string $agentId = null): array
    {
        $session = $this->getSession($sessionId);
        $author = $agentId ? $this->getParticipant($agentId) : null;

        $document = $this->documentService->create($session, $data, $author);

        return $this->documentService->serialize($document, true);
    }

    public function updateDocument(string $documentId, array $data, ?string $agentId = null): array
    {
        $document = $this->getDocument($documentId);
        $author = $agentId ? $this->getParticipant($agentId) : null;

        $changeDescription = $data['change_description'] ?? null;
        unset($data['change_description']);

        $document = $this->documentService->update($document, $data, $author, $changeDescription);

        return $this->documentService->serialize($document, true);
    }

    public function deleteDocument(string $documentId, ?string $agentId = null): void
    {
        $document = $this->getDocument($documentId);
        $this->documentService->delete($document);
    }

    /**
     * @param array{type?: string, status?: string, untreated_only?: bool, author_id?: string} $filters
     */
    public function readAnnotations(string $documentId, array $filters = []): array
    {
        $document = $this->getDocument($documentId);

        $annotations = $this->annotationRepository->findByDocumentWithFilters($document, $filters);

        return array_map(
            fn(Annotation $a) => $this->annotationService->serialize($a),
            $annotations
        );
    }

    /**
     * @param array{type?: string, status?: string, untreated_only?: bool} $filters
     */
    public function getSessionAnnotations(string $sessionId, array $filters = []): array
    {
        $session = $this->getSession($sessionId);

        $annotations = $this->annotationRepository->findBySessionWithFilters($session, $filters);

        return array_map(
            fn(Annotation $a) => $this->annotationService->serialize($a),
            $annotations
        );
    }

    public function respondToAnnotation(string $annotationId, string $content, ?string $agentId = null): array
    {
        $annotation = $this->getAnnotation($annotationId);
        $author = $agentId ? $this->getParticipant($agentId) : null;

        if ($author === null) {
            throw new \InvalidArgumentException('Un auteur est requis pour répondre à une annotation');
        }

        $reply = $this->annotationService->createReply($annotation, $content, $author);

        return $this->annotationService->serialize($reply);
    }

    public function acknowledgeAnnotation(string $annotationId, ?string $agentId = null): array
    {
        $annotation = $this->getAnnotation($annotationId);

        $annotation = $this->annotationService->markAsTakenIntoAccount($annotation);

        return $this->annotationService->serialize($annotation);
    }

    public function getSessionStatus(string $sessionId): array
    {
        $session = $this->getSession($sessionId);

        $openAnnotations = $this->annotationRepository->countBySessionAndStatus($session, Annotation::STATUS_OPEN);
        $pendingDecisions = $this->decisionRepository->countPendingBySession($session);
        $untreatedAnnotations = $this->annotationRepository->countUntreatedBySession($session);

        $threshold = new \DateTimeImmutable('-30 seconds');
        $participants = $this->participantRepository->findOnlineInSession($sessionId, $threshold);

        $decisions = $this->decisionRepository->findBySession($session);

        return [
            'session' => [
                'id' => $session->getId()->toString(),
                'title' => $session->getTitle(),
                'status' => $session->getStatus(),
            ],
            'statistics' => [
                'total_documents' => $session->getDocuments()->count(),
                'open_annotations' => $openAnnotations,
                'untreated_annotations' => $untreatedAnnotations,
                'pending_decisions' => $pendingDecisions,
                'online_participants' => count($participants),
            ],
            'decisions' => array_map(
                fn($d) => $this->decisionService->serialize($d),
                $decisions
            ),
            'priority_annotations' => array_map(
                fn($a) => $this->annotationService->serialize($a),
                $this->annotationRepository->findPriorityAnnotations($session, 5)
            ),
        ];
    }

    private function getSession(string $sessionId): Session
    {
        $session = $this->sessionRepository->find(Uuid::fromString($sessionId));
        if ($session === null) {
            throw new \InvalidArgumentException("Session non trouvée: {$sessionId}");
        }
        return $session;
    }

    private function getDocument(string $documentId): Document
    {
        $document = $this->documentRepository->find(Uuid::fromString($documentId));
        if ($document === null) {
            throw new \InvalidArgumentException("Document non trouvé: {$documentId}");
        }
        return $document;
    }

    private function getAnnotation(string $annotationId): Annotation
    {
        $annotation = $this->annotationRepository->find(Uuid::fromString($annotationId));
        if ($annotation === null) {
            throw new \InvalidArgumentException("Annotation non trouvée: {$annotationId}");
        }
        return $annotation;
    }

    private function getParticipant(string $participantId): Participant
    {
        $participant = $this->participantRepository->find(Uuid::fromString($participantId));
        if ($participant === null) {
            throw new \InvalidArgumentException("Participant non trouvé: {$participantId}");
        }
        return $participant;
    }
}

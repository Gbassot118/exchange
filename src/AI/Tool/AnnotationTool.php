<?php

namespace App\AI\Tool;

use App\Entity\Annotation;
use App\Repository\AnnotationRepository;
use App\Repository\DocumentRepository;
use App\Repository\ParticipantRepository;
use App\Repository\SessionRepository;
use App\Service\Annotation\AnnotationService;
use Symfony\AI\Attribute\AsTool;
use Symfony\Component\Uid\Uuid;

#[AsTool(
    name: 'annotation_operations',
    description: 'Tool for managing annotations in a collaborative documentation session. Supports reading annotations, responding to them, and acknowledging them.',
    parameters: [
        'operation' => [
            'type' => 'string',
            'description' => 'The operation to perform: list, read, respond, acknowledge, resolve',
            'enum' => ['list', 'read', 'respond', 'acknowledge', 'resolve'],
            'required' => true,
        ],
        'session_id' => [
            'type' => 'string',
            'description' => 'The session UUID (required for list operation)',
        ],
        'document_id' => [
            'type' => 'string',
            'description' => 'The document UUID (for filtering annotations)',
        ],
        'annotation_id' => [
            'type' => 'string',
            'description' => 'The annotation UUID (required for read, respond, acknowledge, resolve)',
        ],
        'participant_id' => [
            'type' => 'string',
            'description' => 'The AI agent participant UUID (required for respond, acknowledge, resolve)',
        ],
        'content' => [
            'type' => 'string',
            'description' => 'Response content (required for respond operation)',
        ],
        'type_filter' => [
            'type' => 'string',
            'description' => 'Filter annotations by type',
            'enum' => ['question', 'objection', 'suggestion', 'comment', 'validation'],
        ],
        'status_filter' => [
            'type' => 'string',
            'description' => 'Filter annotations by status',
            'enum' => ['open', 'resolved', 'acknowledged'],
        ],
    ]
)]
class AnnotationTool
{
    public function __construct(
        private readonly AnnotationService $annotationService,
        private readonly AnnotationRepository $annotationRepository,
        private readonly DocumentRepository $documentRepository,
        private readonly SessionRepository $sessionRepository,
        private readonly ParticipantRepository $participantRepository,
    ) {}

    public function __invoke(
        string $operation,
        ?string $session_id = null,
        ?string $document_id = null,
        ?string $annotation_id = null,
        ?string $participant_id = null,
        ?string $content = null,
        ?string $type_filter = null,
        ?string $status_filter = null,
    ): array {
        return match ($operation) {
            'list' => $this->listAnnotations($session_id, $document_id, $type_filter, $status_filter),
            'read' => $this->readAnnotation($annotation_id),
            'respond' => $this->respondToAnnotation($annotation_id, $participant_id, $content),
            'acknowledge' => $this->acknowledgeAnnotation($annotation_id, $participant_id),
            'resolve' => $this->resolveAnnotation($annotation_id, $participant_id),
            default => ['error' => 'Unknown operation: ' . $operation],
        };
    }

    private function listAnnotations(
        ?string $sessionId,
        ?string $documentId,
        ?string $typeFilter,
        ?string $statusFilter,
    ): array {
        $filters = array_filter([
            'type' => $typeFilter,
            'status' => $statusFilter,
        ]);

        try {
            if (!empty($documentId)) {
                $document = $this->documentRepository->find(Uuid::fromString($documentId));
                if ($document === null) {
                    return ['error' => 'Document not found'];
                }
                $annotations = $this->annotationRepository->findByDocumentWithFilters($document, $filters);
            } elseif (!empty($sessionId)) {
                $session = $this->sessionRepository->find(Uuid::fromString($sessionId));
                if ($session === null) {
                    return ['error' => 'Session not found'];
                }
                $annotations = $this->annotationRepository->findBySessionWithFilters($session, $filters);
            } else {
                return ['error' => 'Either session_id or document_id is required for list operation'];
            }

            return [
                'annotations' => array_map(fn($ann) => $this->serializeAnnotation($ann), $annotations),
            ];
        } catch (\InvalidArgumentException $e) {
            return ['error' => 'Invalid UUID format'];
        }
    }

    private function readAnnotation(?string $annotationId): array
    {
        if (empty($annotationId)) {
            return ['error' => 'annotation_id is required for read operation'];
        }

        try {
            $annotation = $this->annotationRepository->find(Uuid::fromString($annotationId));
            if ($annotation === null) {
                return ['error' => 'Annotation not found'];
            }

            $data = $this->serializeAnnotation($annotation);
            $data['replies'] = array_map(
                fn($r) => $this->serializeAnnotation($r),
                $annotation->getReplies()->toArray()
            );

            return ['annotation' => $data];
        } catch (\InvalidArgumentException $e) {
            return ['error' => 'Invalid annotation_id format'];
        }
    }

    private function respondToAnnotation(
        ?string $annotationId,
        ?string $participantId,
        ?string $content,
    ): array {
        if (empty($annotationId)) {
            return ['error' => 'annotation_id is required for respond operation'];
        }
        if (empty($participantId)) {
            return ['error' => 'participant_id is required for respond operation'];
        }
        if (empty($content)) {
            return ['error' => 'content is required for respond operation'];
        }

        try {
            $annotation = $this->annotationRepository->find(Uuid::fromString($annotationId));
            if ($annotation === null) {
                return ['error' => 'Annotation not found'];
            }

            $participant = $this->participantRepository->find(Uuid::fromString($participantId));
            if ($participant === null) {
                return ['error' => 'Participant not found'];
            }

            $reply = $this->annotationService->createReply($annotation, $content, $participant);

            return [
                'success' => true,
                'reply' => $this->serializeAnnotation($reply),
            ];
        } catch (\InvalidArgumentException $e) {
            return ['error' => 'Invalid UUID format'];
        } catch (\Exception $e) {
            return ['error' => $e->getMessage()];
        }
    }

    private function acknowledgeAnnotation(?string $annotationId, ?string $participantId): array
    {
        if (empty($annotationId)) {
            return ['error' => 'annotation_id is required for acknowledge operation'];
        }
        if (empty($participantId)) {
            return ['error' => 'participant_id is required for acknowledge operation'];
        }

        try {
            $annotation = $this->annotationRepository->find(Uuid::fromString($annotationId));
            if ($annotation === null) {
                return ['error' => 'Annotation not found'];
            }

            $participant = $this->participantRepository->find(Uuid::fromString($participantId));
            if ($participant === null) {
                return ['error' => 'Participant not found'];
            }

            $annotation = $this->annotationService->setStatus($annotation, Annotation::STATUS_ACKNOWLEDGED);

            return [
                'success' => true,
                'annotation' => $this->serializeAnnotation($annotation),
            ];
        } catch (\InvalidArgumentException $e) {
            return ['error' => 'Invalid UUID format'];
        } catch (\Exception $e) {
            return ['error' => $e->getMessage()];
        }
    }

    private function resolveAnnotation(?string $annotationId, ?string $participantId): array
    {
        if (empty($annotationId)) {
            return ['error' => 'annotation_id is required for resolve operation'];
        }
        if (empty($participantId)) {
            return ['error' => 'participant_id is required for resolve operation'];
        }

        try {
            $annotation = $this->annotationRepository->find(Uuid::fromString($annotationId));
            if ($annotation === null) {
                return ['error' => 'Annotation not found'];
            }

            $participant = $this->participantRepository->find(Uuid::fromString($participantId));
            if ($participant === null) {
                return ['error' => 'Participant not found'];
            }

            $annotation = $this->annotationService->resolve($annotation, $participant);

            return [
                'success' => true,
                'annotation' => $this->serializeAnnotation($annotation),
            ];
        } catch (\InvalidArgumentException $e) {
            return ['error' => 'Invalid UUID format'];
        } catch (\Exception $e) {
            return ['error' => $e->getMessage()];
        }
    }

    private function serializeAnnotation(Annotation $annotation): array
    {
        return [
            'id' => $annotation->getId()->toString(),
            'content' => $annotation->getContent(),
            'type' => $annotation->getType(),
            'status' => $annotation->getStatus(),
            'author' => [
                'id' => $annotation->getAuthor()->getId()->toString(),
                'pseudo' => $annotation->getAuthor()->getPseudo(),
                'is_agent' => $annotation->getAuthor()->isAgent(),
            ],
            'document_id' => $annotation->getDocument()->getId()->toString(),
            'document_title' => $annotation->getDocument()->getTitle(),
            'anchor' => $annotation->getAnchor(),
            'taken_into_account' => $annotation->isTakenIntoAccount(),
            'created_at' => $annotation->getCreatedAt()->format('c'),
            'replies_count' => $annotation->getReplies()->count(),
        ];
    }
}

<?php

declare(strict_types=1);

namespace App\AI\Tool;

use App\Application\Command\Annotation\RespondAnnotationCommand;
use App\Application\Command\Annotation\ResolveAnnotationCommand;
use App\Application\DTO\Response\AnnotationResponse;
use App\Application\Query\Annotation\GetAnnotationQuery;
use App\Application\Query\Annotation\ListAnnotationsQuery;
use Symfony\AI\Attribute\AsTool;
use Symfony\Component\Messenger\Exception\ExceptionInterface;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\HandledStamp;

#[AsTool(
    name: 'annotation_operations',
    description: 'Tool for managing comments in a collaborative documentation session. Supports reading comments, responding to them, and resolving them.',
    parameters: [
        'operation' => [
            'type' => 'string',
            'description' => 'The operation to perform: list, read, respond, resolve',
            'enum' => ['list', 'read', 'respond', 'resolve'],
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
            'description' => 'The annotation UUID (required for read, respond, resolve)',
        ],
        'participant_id' => [
            'type' => 'string',
            'description' => 'The AI agent participant UUID (required for respond, resolve)',
        ],
        'content' => [
            'type' => 'string',
            'description' => 'Response content (required for respond operation)',
        ],
        'status_filter' => [
            'type' => 'string',
            'description' => 'Filter annotations by status',
            'enum' => ['open', 'in_progress', 'resolved'],
        ],
    ]
)]
class AnnotationTool
{
    public function __construct(
        private readonly MessageBusInterface $messageBus,
    ) {}

    public function __invoke(
        string $operation,
        ?string $session_id = null,
        ?string $document_id = null,
        ?string $annotation_id = null,
        ?string $participant_id = null,
        ?string $content = null,
        ?string $status_filter = null,
    ): array {
        return match ($operation) {
            'list' => $this->listAnnotations($session_id, $document_id, $status_filter),
            'read' => $this->readAnnotation($annotation_id),
            'respond' => $this->respondToAnnotation($annotation_id, $participant_id, $content),
            'resolve' => $this->resolveAnnotation($annotation_id, $participant_id),
            default => ['error' => 'Unknown operation: ' . $operation],
        };
    }

    private function listAnnotations(
        ?string $sessionId,
        ?string $documentId,
        ?string $statusFilter,
    ): array {
        if (empty($sessionId) && empty($documentId)) {
            return ['error' => 'Either session_id or document_id is required for list operation'];
        }

        // If only documentId is provided, we need sessionId too for the query
        // The ListAnnotationsQuery requires sessionId
        if (empty($sessionId)) {
            return ['error' => 'session_id is required for list operation'];
        }

        try {
            $filters = [];
            if ($statusFilter !== null) {
                $filters['status'] = $statusFilter;
            }

            $query = new ListAnnotationsQuery(
                sessionId: $sessionId,
                documentId: $documentId,
                filters: $filters,
                includeReplies: true,
            );

            $envelope = $this->messageBus->dispatch($query);
            $handledStamp = $envelope->last(HandledStamp::class);

            if ($handledStamp === null) {
                return ['error' => 'Query was not handled'];
            }

            /** @var array<AnnotationResponse> $annotations */
            $annotations = $handledStamp->getResult();

            return [
                'annotations' => array_map(
                    fn(AnnotationResponse $ann) => $this->serializeAnnotation($ann),
                    $annotations
                ),
            ];
        } catch (ExceptionInterface $e) {
            return ['error' => $e->getMessage()];
        } catch (\Exception $e) {
            return ['error' => $e->getMessage()];
        }
    }

    private function readAnnotation(?string $annotationId): array
    {
        if (empty($annotationId)) {
            return ['error' => 'annotation_id is required for read operation'];
        }

        try {
            $query = new GetAnnotationQuery(
                annotationId: $annotationId,
                includeReplies: true,
            );

            $envelope = $this->messageBus->dispatch($query);
            $handledStamp = $envelope->last(HandledStamp::class);

            if ($handledStamp === null) {
                return ['error' => 'Query was not handled'];
            }

            /** @var AnnotationResponse $annotation */
            $annotation = $handledStamp->getResult();

            $data = $this->serializeAnnotation($annotation);
            $data['replies'] = array_map(
                fn(AnnotationResponse $r) => $this->serializeAnnotation($r),
                $annotation->replies ?? []
            );

            return ['annotation' => $data];
        } catch (ExceptionInterface $e) {
            return ['error' => $e->getMessage()];
        } catch (\Exception $e) {
            return ['error' => $e->getMessage()];
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
            $command = new RespondAnnotationCommand(
                annotationId: $annotationId,
                authorParticipantId: $participantId,
                content: $content,
            );

            $envelope = $this->messageBus->dispatch($command);
            $handledStamp = $envelope->last(HandledStamp::class);

            if ($handledStamp === null) {
                return ['error' => 'Command was not handled'];
            }

            /** @var AnnotationResponse $reply */
            $reply = $handledStamp->getResult();

            return [
                'success' => true,
                'reply' => $this->serializeAnnotation($reply),
            ];
        } catch (ExceptionInterface $e) {
            return ['error' => $e->getMessage()];
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
            $command = new ResolveAnnotationCommand(
                annotationId: $annotationId,
                resolvedByParticipantId: $participantId,
            );

            $envelope = $this->messageBus->dispatch($command);
            $handledStamp = $envelope->last(HandledStamp::class);

            if ($handledStamp === null) {
                return ['error' => 'Command was not handled'];
            }

            /** @var AnnotationResponse $annotation */
            $annotation = $handledStamp->getResult();

            return [
                'success' => true,
                'annotation' => $this->serializeAnnotation($annotation),
            ];
        } catch (ExceptionInterface $e) {
            return ['error' => $e->getMessage()];
        } catch (\Exception $e) {
            return ['error' => $e->getMessage()];
        }
    }

    private function serializeAnnotation(AnnotationResponse $annotation): array
    {
        return [
            'id' => $annotation->id,
            'content' => $annotation->content,
            'type' => $annotation->type,
            'status' => $annotation->status,
            'author' => $annotation->author,
            'document_id' => $annotation->documentId,
            'anchor' => $annotation->anchor ?? null,
            'taken_into_account' => $annotation->takenIntoAccount,
            'created_at' => $annotation->createdAt,
            'replies_count' => $annotation->replyCount,
        ];
    }
}

<?php

namespace App\Controller\Api;

use App\Application\Command\Annotation\AcknowledgeAnnotationCommand;
use App\Application\Command\Annotation\AcknowledgeAnnotationHandler;
use App\Application\Command\Annotation\RespondAnnotationCommand;
use App\Application\Command\Annotation\RespondAnnotationHandler;
use App\Application\Command\Document\CreateDocumentCommand;
use App\Application\Command\Document\CreateDocumentHandler;
use App\Application\Command\Document\DeleteDocumentCommand;
use App\Application\Command\Document\DeleteDocumentHandler;
use App\Application\Command\Document\UpdateDocumentCommand;
use App\Application\Command\Document\UpdateDocumentHandler;
use App\Application\Command\Session\UpdateSessionStatusCommand;
use App\Application\Command\Session\UpdateSessionStatusHandler;
use App\Application\DTO\Request\CreateDocumentRequest;
use App\Application\DTO\Request\UpdateDocumentRequest;
use App\Application\DTO\Request\UpdateSessionStatusRequest;
use App\Application\Query\Annotation\ListAnnotationsQuery;
use App\Application\Query\Annotation\ListAnnotationsHandler;
use App\Application\Query\Document\GetDocumentQuery;
use App\Application\Query\Document\GetDocumentHandler;
use App\Application\Query\Document\ListDocumentsQuery;
use App\Application\Query\Document\ListDocumentsHandler;
use App\Application\Query\Session\GetSessionStatusQuery;
use App\Application\Query\Session\GetSessionStatusHandler;
use App\Domain\Collaboration\Exception\AnnotationNotFoundException;
use App\Domain\Collaboration\Exception\CannotReplyToReplyException;
use App\Domain\Document\Exception\DocumentNotFoundException;
use App\Domain\Session\Exception\InvalidSessionStatusTransitionException;
use App\Domain\Session\Exception\ParticipantNotFoundException;
use App\Domain\Session\Exception\SessionNotFoundException;
use App\Security\InvalidParticipantException;
use App\Security\ParticipantValidator;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/mcp', name: 'api_mcp_')]
class McpController extends AbstractController
{
    public function __construct(
        private readonly ListDocumentsHandler $listDocumentsHandler,
        private readonly GetDocumentHandler $getDocumentHandler,
        private readonly CreateDocumentHandler $createDocumentHandler,
        private readonly UpdateDocumentHandler $updateDocumentHandler,
        private readonly DeleteDocumentHandler $deleteDocumentHandler,
        private readonly ListAnnotationsHandler $listAnnotationsHandler,
        private readonly GetSessionStatusHandler $getSessionStatusHandler,
        private readonly UpdateSessionStatusHandler $updateSessionStatusHandler,
        private readonly RespondAnnotationHandler $respondAnnotationHandler,
        private readonly AcknowledgeAnnotationHandler $acknowledgeAnnotationHandler,
        private readonly ParticipantValidator $participantValidator,
    ) {}

    #[Route('/sessions/{sessionId}/documents', name: 'list_documents', methods: ['GET'])]
    public function listDocuments(string $sessionId, Request $request): JsonResponse
    {
        try {
            $query = new ListDocumentsQuery(
                sessionId: $sessionId,
                parentId: $request->query->get('parent_id'),
                type: $request->query->get('type'),
                includeContent: false,
            );

            $documents = ($this->listDocumentsHandler)($query);

            return $this->json([
                'documents' => array_map(fn($d) => $d->toArray(), $documents),
            ]);
        } catch (SessionNotFoundException $e) {
            return $this->json(['error' => $e->getMessage()], Response::HTTP_NOT_FOUND);
        } catch (\InvalidArgumentException $e) {
            return $this->json(['error' => $e->getMessage()], Response::HTTP_BAD_REQUEST);
        }
    }

    #[Route('/documents/{documentId}', name: 'read_document', methods: ['GET'])]
    public function readDocument(string $documentId, Request $request): JsonResponse
    {
        try {
            $query = new GetDocumentQuery(
                documentId: $documentId,
                includeContent: true,
                includeAnnotations: $request->query->getBoolean('include_annotations', false),
                includeVersions: $request->query->getBoolean('include_versions', false),
            );

            $document = ($this->getDocumentHandler)($query);

            return $this->json($document->toArray());
        } catch (DocumentNotFoundException $e) {
            return $this->json(['error' => $e->getMessage()], Response::HTTP_NOT_FOUND);
        } catch (\InvalidArgumentException $e) {
            return $this->json(['error' => $e->getMessage()], Response::HTTP_BAD_REQUEST);
        }
    }

    #[Route('/sessions/{sessionId}/documents', name: 'create_document', methods: ['POST'])]
    public function createDocument(string $sessionId, Request $request): JsonResponse
    {
        try {
            $data = $request->toArray();
            $dto = CreateDocumentRequest::fromArray($data);
            $agentId = $request->headers->get('X-Agent-Id');

            if (empty($dto->title)) {
                return $this->json(['error' => 'Le titre est requis'], Response::HTTP_BAD_REQUEST);
            }

            // Validate that the agent belongs to this session (write operation)
            if (!empty($agentId)) {
                $this->participantValidator->validateParticipantInSession($agentId, $sessionId);
            }

            $command = new CreateDocumentCommand(
                sessionId: $sessionId,
                title: $dto->title,
                type: $dto->type ?: 'general',
                content: $dto->content,
                metadata: $dto->metadata,
                parentId: $dto->parentId,
                sortOrder: $dto->sortOrder,
                authorParticipantId: $agentId,
            );

            $document = ($this->createDocumentHandler)($command);

            return $this->json($document->toArray(), Response::HTTP_CREATED);
        } catch (InvalidParticipantException $e) {
            return $this->json(['error' => $e->getMessage()], $e->getStatusCode());
        } catch (SessionNotFoundException|DocumentNotFoundException $e) {
            return $this->json(['error' => $e->getMessage()], Response::HTTP_NOT_FOUND);
        } catch (\InvalidArgumentException $e) {
            return $this->json(['error' => $e->getMessage()], Response::HTTP_BAD_REQUEST);
        }
    }

    #[Route('/documents/{documentId}', name: 'update_document', methods: ['PUT', 'PATCH'])]
    public function updateDocument(string $documentId, Request $request): JsonResponse
    {
        try {
            $data = $request->toArray();
            $dto = UpdateDocumentRequest::fromArray($data);
            $agentId = $request->headers->get('X-Agent-Id');

            // Validate that the agent has access to this document (write operation)
            if (!empty($agentId)) {
                $this->participantValidator->validateAgentWriteAccess($agentId, $documentId);
            }

            $command = new UpdateDocumentCommand(
                documentId: $documentId,
                title: $dto->title,
                content: $dto->content,
                metadata: $dto->metadata,
                parentId: $dto->parentId,
                sortOrder: $dto->sortOrder,
                changeDescription: $dto->changeDescription,
                authorParticipantId: $agentId,
            );

            $document = ($this->updateDocumentHandler)($command);

            return $this->json($document->toArray());
        } catch (InvalidParticipantException $e) {
            return $this->json(['error' => $e->getMessage()], $e->getStatusCode());
        } catch (DocumentNotFoundException $e) {
            return $this->json(['error' => $e->getMessage()], Response::HTTP_NOT_FOUND);
        } catch (\InvalidArgumentException $e) {
            return $this->json(['error' => $e->getMessage()], Response::HTTP_BAD_REQUEST);
        }
    }

    #[Route('/documents/{documentId}', name: 'delete_document', methods: ['DELETE'])]
    public function deleteDocument(string $documentId, Request $request): JsonResponse
    {
        try {
            $agentId = $request->headers->get('X-Agent-Id');

            // Validate that the agent has access to this document (write operation)
            if (!empty($agentId)) {
                $this->participantValidator->validateAgentWriteAccess($agentId, $documentId);
            }

            $command = new DeleteDocumentCommand(documentId: $documentId);
            ($this->deleteDocumentHandler)($command);

            return $this->json(null, Response::HTTP_NO_CONTENT);
        } catch (InvalidParticipantException $e) {
            return $this->json(['error' => $e->getMessage()], $e->getStatusCode());
        } catch (DocumentNotFoundException $e) {
            return $this->json(['error' => $e->getMessage()], Response::HTTP_NOT_FOUND);
        } catch (\InvalidArgumentException $e) {
            return $this->json(['error' => $e->getMessage()], Response::HTTP_BAD_REQUEST);
        }
    }

    #[Route('/documents/{documentId}/annotations', name: 'read_annotations', methods: ['GET'])]
    public function readAnnotations(string $documentId, Request $request): JsonResponse
    {
        try {
            $filters = array_filter([
                'type' => $request->query->get('type'),
                'status' => $request->query->get('status'),
                'untreated_only' => $request->query->getBoolean('untreated_only', false) ?: null,
                'author_id' => $request->query->get('author_id'),
            ]);

            $query = new ListAnnotationsQuery(
                sessionId: '',
                documentId: $documentId,
                filters: $filters,
                includeReplies: true,
            );

            $annotations = ($this->listAnnotationsHandler)($query);

            return $this->json([
                'annotations' => array_map(fn($a) => $a->toArray(), $annotations),
            ]);
        } catch (DocumentNotFoundException $e) {
            return $this->json(['error' => $e->getMessage()], Response::HTTP_NOT_FOUND);
        } catch (\InvalidArgumentException $e) {
            return $this->json(['error' => $e->getMessage()], Response::HTTP_BAD_REQUEST);
        }
    }

    #[Route('/sessions/{sessionId}/annotations', name: 'session_annotations', methods: ['GET'])]
    public function sessionAnnotations(string $sessionId, Request $request): JsonResponse
    {
        try {
            $filters = array_filter([
                'type' => $request->query->get('type'),
                'status' => $request->query->get('status'),
                'untreated_only' => $request->query->getBoolean('untreated_only', false) ?: null,
            ]);

            $query = new ListAnnotationsQuery(
                sessionId: $sessionId,
                documentId: null,
                filters: $filters,
                includeReplies: true,
            );

            $annotations = ($this->listAnnotationsHandler)($query);

            return $this->json([
                'annotations' => array_map(fn($a) => $a->toArray(), $annotations),
            ]);
        } catch (SessionNotFoundException $e) {
            return $this->json(['error' => $e->getMessage()], Response::HTTP_NOT_FOUND);
        } catch (\InvalidArgumentException $e) {
            return $this->json(['error' => $e->getMessage()], Response::HTTP_BAD_REQUEST);
        }
    }

    #[Route('/sessions/{sessionId}/status', name: 'get_session_status', methods: ['GET'])]
    public function getSessionStatus(string $sessionId): JsonResponse
    {
        try {
            $query = new GetSessionStatusQuery(sessionId: $sessionId);
            $status = ($this->getSessionStatusHandler)($query);

            return $this->json($status->toArray());
        } catch (SessionNotFoundException $e) {
            return $this->json(['error' => $e->getMessage()], Response::HTTP_NOT_FOUND);
        } catch (\InvalidArgumentException $e) {
            return $this->json(['error' => $e->getMessage()], Response::HTTP_BAD_REQUEST);
        }
    }

    #[Route('/sessions/{sessionId}/status', name: 'update_session_status', methods: ['PATCH'])]
    public function updateSessionStatus(string $sessionId, Request $request): JsonResponse
    {
        try {
            $data = $request->toArray();

            if (empty($data['status'])) {
                return $this->json([
                    'error' => 'Le statut est requis',
                    'valid_statuses' => ['preparation', 'en_cours', 'termine', 'archive'],
                    'hint' => 'Utilisez "en_cours" quand vous commencez à travailler sur la session',
                ], Response::HTTP_BAD_REQUEST);
            }

            $dto = UpdateSessionStatusRequest::fromArray($data);
            $command = new UpdateSessionStatusCommand(
                sessionId: $sessionId,
                newStatus: $dto->toSessionStatus(),
            );

            $session = ($this->updateSessionStatusHandler)($command);

            return $this->json($session->toArray());
        } catch (SessionNotFoundException $e) {
            return $this->json(['error' => $e->getMessage()], Response::HTTP_NOT_FOUND);
        } catch (InvalidSessionStatusTransitionException $e) {
            return $this->json(['error' => $e->getMessage()], Response::HTTP_BAD_REQUEST);
        } catch (\InvalidArgumentException $e) {
            return $this->json(['error' => $e->getMessage()], Response::HTTP_BAD_REQUEST);
        }
    }

    #[Route('/annotations/{annotationId}/respond', name: 'respond_annotation', methods: ['POST'])]
    public function respondToAnnotation(string $annotationId, Request $request): JsonResponse
    {
        try {
            $data = $request->toArray();
            $agentId = $request->headers->get('X-Agent-Id');

            if (empty($data['content'])) {
                return $this->json(['error' => 'Le contenu est requis'], Response::HTTP_BAD_REQUEST);
            }

            if (empty($agentId)) {
                return $this->json(['error' => 'X-Agent-Id header est requis'], Response::HTTP_BAD_REQUEST);
            }

            // Validate that the agent has access to this annotation
            $this->participantValidator->validateParticipantAccessToAnnotation($agentId, $annotationId);

            $command = new RespondAnnotationCommand(
                annotationId: $annotationId,
                authorParticipantId: $agentId,
                content: $data['content'],
                markAsResolved: $data['mark_as_resolved'] ?? false,
            );

            $reply = ($this->respondAnnotationHandler)($command);

            return $this->json($reply->toArray(), Response::HTTP_CREATED);
        } catch (InvalidParticipantException $e) {
            return $this->json(['error' => $e->getMessage()], $e->getStatusCode());
        } catch (AnnotationNotFoundException|ParticipantNotFoundException $e) {
            return $this->json(['error' => $e->getMessage()], Response::HTTP_NOT_FOUND);
        } catch (CannotReplyToReplyException $e) {
            return $this->json(['error' => $e->getMessage()], Response::HTTP_BAD_REQUEST);
        } catch (\InvalidArgumentException $e) {
            return $this->json(['error' => $e->getMessage()], Response::HTTP_BAD_REQUEST);
        }
    }

    #[Route('/annotations/{annotationId}/acknowledge', name: 'acknowledge_annotation', methods: ['POST'])]
    public function acknowledgeAnnotation(string $annotationId): JsonResponse
    {
        try {
            $command = new AcknowledgeAnnotationCommand(
                annotationId: $annotationId,
                acknowledged: true,
            );

            $annotation = ($this->acknowledgeAnnotationHandler)($command);

            return $this->json($annotation->toArray());
        } catch (AnnotationNotFoundException $e) {
            return $this->json(['error' => $e->getMessage()], Response::HTTP_NOT_FOUND);
        } catch (\InvalidArgumentException $e) {
            return $this->json(['error' => $e->getMessage()], Response::HTTP_BAD_REQUEST);
        }
    }
}

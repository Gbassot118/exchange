<?php

namespace App\Controller\Api;

use App\Service\Mcp\McpService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/mcp', name: 'api_mcp_')]
class McpController extends AbstractController
{
    public function __construct(
        private readonly McpService $mcpService,
    ) {}

    #[Route('/sessions/{sessionId}/documents', name: 'list_documents', methods: ['GET'])]
    public function listDocuments(string $sessionId, Request $request): JsonResponse
    {
        try {
            $parentId = $request->query->get('parent_id');
            $type = $request->query->get('type');

            $documents = $this->mcpService->listDocuments($sessionId, $parentId, $type);

            return $this->json(['documents' => $documents]);
        } catch (\InvalidArgumentException $e) {
            return $this->json(['error' => $e->getMessage()], Response::HTTP_NOT_FOUND);
        }
    }

    #[Route('/documents/{documentId}', name: 'read_document', methods: ['GET'])]
    public function readDocument(string $documentId, Request $request): JsonResponse
    {
        try {
            $includeAnnotations = $request->query->getBoolean('include_annotations', false);
            $includeVersions = $request->query->getBoolean('include_versions', false);

            $document = $this->mcpService->readDocument($documentId, $includeAnnotations, $includeVersions);

            return $this->json($document);
        } catch (\InvalidArgumentException $e) {
            return $this->json(['error' => $e->getMessage()], Response::HTTP_NOT_FOUND);
        }
    }

    #[Route('/sessions/{sessionId}/documents', name: 'create_document', methods: ['POST'])]
    public function createDocument(string $sessionId, Request $request): JsonResponse
    {
        try {
            $data = $request->toArray();
            $agentId = $request->headers->get('X-Agent-Id');

            if (empty($data['title'])) {
                return $this->json(['error' => 'Le titre est requis'], Response::HTTP_BAD_REQUEST);
            }

            $document = $this->mcpService->createDocument($sessionId, $data, $agentId);

            return $this->json($document, Response::HTTP_CREATED);
        } catch (\InvalidArgumentException $e) {
            return $this->json(['error' => $e->getMessage()], Response::HTTP_NOT_FOUND);
        }
    }

    #[Route('/documents/{documentId}', name: 'update_document', methods: ['PUT', 'PATCH'])]
    public function updateDocument(string $documentId, Request $request): JsonResponse
    {
        try {
            $data = $request->toArray();
            $agentId = $request->headers->get('X-Agent-Id');

            $document = $this->mcpService->updateDocument($documentId, $data, $agentId);

            return $this->json($document);
        } catch (\InvalidArgumentException $e) {
            return $this->json(['error' => $e->getMessage()], Response::HTTP_NOT_FOUND);
        }
    }

    #[Route('/documents/{documentId}', name: 'delete_document', methods: ['DELETE'])]
    public function deleteDocument(string $documentId, Request $request): JsonResponse
    {
        try {
            $agentId = $request->headers->get('X-Agent-Id');

            $this->mcpService->deleteDocument($documentId, $agentId);

            return $this->json(null, Response::HTTP_NO_CONTENT);
        } catch (\InvalidArgumentException $e) {
            return $this->json(['error' => $e->getMessage()], Response::HTTP_NOT_FOUND);
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

            $annotations = $this->mcpService->readAnnotations($documentId, $filters);

            return $this->json(['annotations' => $annotations]);
        } catch (\InvalidArgumentException $e) {
            return $this->json(['error' => $e->getMessage()], Response::HTTP_NOT_FOUND);
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

            $annotations = $this->mcpService->getSessionAnnotations($sessionId, $filters);

            return $this->json(['annotations' => $annotations]);
        } catch (\InvalidArgumentException $e) {
            return $this->json(['error' => $e->getMessage()], Response::HTTP_NOT_FOUND);
        }
    }

    #[Route('/sessions/{sessionId}/status', name: 'get_session_status', methods: ['GET'])]
    public function getSessionStatus(string $sessionId): JsonResponse
    {
        try {
            $status = $this->mcpService->getSessionStatus($sessionId);

            return $this->json($status);
        } catch (\InvalidArgumentException $e) {
            return $this->json(['error' => $e->getMessage()], Response::HTTP_NOT_FOUND);
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

            $reply = $this->mcpService->respondToAnnotation($annotationId, $data['content'], $agentId);

            return $this->json($reply, Response::HTTP_CREATED);
        } catch (\InvalidArgumentException $e) {
            return $this->json(['error' => $e->getMessage()], Response::HTTP_NOT_FOUND);
        }
    }

    #[Route('/annotations/{annotationId}/acknowledge', name: 'acknowledge_annotation', methods: ['POST'])]
    public function acknowledgeAnnotation(string $annotationId, Request $request): JsonResponse
    {
        try {
            $agentId = $request->headers->get('X-Agent-Id');

            $annotation = $this->mcpService->acknowledgeAnnotation($annotationId, $agentId);

            return $this->json($annotation);
        } catch (\InvalidArgumentException $e) {
            return $this->json(['error' => $e->getMessage()], Response::HTTP_NOT_FOUND);
        }
    }
}

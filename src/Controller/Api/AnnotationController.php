<?php

namespace App\Controller\Api;

use App\Entity\Annotation;
use App\Repository\AnnotationRepository;
use App\Repository\DocumentRepository;
use App\Repository\ParticipantRepository;
use App\Service\Annotation\AnnotationService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Uid\Uuid;

#[Route('/api/annotations', name: 'api_annotations_')]
class AnnotationController extends AbstractController
{
    public function __construct(
        private readonly AnnotationService $annotationService,
        private readonly AnnotationRepository $annotationRepository,
        private readonly DocumentRepository $documentRepository,
        private readonly ParticipantRepository $participantRepository,
    ) {}

    #[Route('', name: 'create', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        // Support both JSON and form data
        $contentType = $request->headers->get('Content-Type', '');
        if (str_contains($contentType, 'application/json')) {
            $data = $request->toArray();
        } else {
            $data = $request->request->all();
        }

        if (empty($data['document_id'])) {
            return $this->json(['error' => 'document_id est requis'], Response::HTTP_BAD_REQUEST);
        }

        if (empty($data['participant_id'])) {
            return $this->json(['error' => 'participant_id est requis'], Response::HTTP_BAD_REQUEST);
        }

        if (empty($data['content'])) {
            return $this->json(['error' => 'Le contenu est requis'], Response::HTTP_BAD_REQUEST);
        }

        try {
            $document = $this->documentRepository->find(Uuid::fromString($data['document_id']));
            if ($document === null) {
                return $this->json(['error' => 'Document non trouvé'], Response::HTTP_NOT_FOUND);
            }

            $participant = $this->participantRepository->find(Uuid::fromString($data['participant_id']));
            if ($participant === null) {
                return $this->json(['error' => 'Participant non trouvé'], Response::HTTP_NOT_FOUND);
            }

            $type = $data['type'] ?? Annotation::TYPE_COMMENT;
            if (!in_array($type, Annotation::TYPES)) {
                return $this->json(['error' => 'Type d\'annotation invalide'], Response::HTTP_BAD_REQUEST);
            }

            $annotation = $this->annotationService->create(
                $document,
                $participant,
                $data['content'],
                $type,
                $data['anchor'] ?? null
            );

            return $this->json($this->annotationService->serialize($annotation), Response::HTTP_CREATED);
        } catch (\InvalidArgumentException $e) {
            return $this->json(['error' => $e->getMessage()], Response::HTTP_BAD_REQUEST);
        }
    }

    #[Route('/{id}', name: 'show', methods: ['GET'])]
    public function show(string $id): JsonResponse
    {
        try {
            $annotation = $this->annotationRepository->find(Uuid::fromString($id));

            if ($annotation === null) {
                return $this->json(['error' => 'Annotation non trouvée'], Response::HTTP_NOT_FOUND);
            }

            $data = $this->annotationService->serialize($annotation);
            $data['replies'] = array_map(
                fn($r) => $this->annotationService->serialize($r),
                $annotation->getReplies()->toArray()
            );

            return $this->json($data);
        } catch (\InvalidArgumentException $e) {
            return $this->json(['error' => 'ID invalide'], Response::HTTP_BAD_REQUEST);
        }
    }

    #[Route('/{id}', name: 'update', methods: ['PUT', 'PATCH'])]
    public function update(string $id, Request $request): JsonResponse
    {
        try {
            $annotation = $this->annotationRepository->find(Uuid::fromString($id));

            if ($annotation === null) {
                return $this->json(['error' => 'Annotation non trouvée'], Response::HTTP_NOT_FOUND);
            }

            $data = $request->toArray();

            if (!empty($data['content'])) {
                $annotation = $this->annotationService->update($annotation, $data['content']);
            }

            if (!empty($data['status']) && in_array($data['status'], Annotation::STATUSES)) {
                $annotation = $this->annotationService->setStatus($annotation, $data['status']);
            }

            return $this->json($this->annotationService->serialize($annotation));
        } catch (\InvalidArgumentException $e) {
            return $this->json(['error' => $e->getMessage()], Response::HTTP_BAD_REQUEST);
        }
    }

    #[Route('/{id}/resolve', name: 'resolve', methods: ['POST'])]
    public function resolve(string $id, Request $request): JsonResponse
    {
        try {
            $annotation = $this->annotationRepository->find(Uuid::fromString($id));

            if ($annotation === null) {
                return $this->json(['error' => 'Annotation non trouvée'], Response::HTTP_NOT_FOUND);
            }

            $data = $request->toArray();

            if (empty($data['participant_id'])) {
                return $this->json(['error' => 'participant_id est requis'], Response::HTTP_BAD_REQUEST);
            }

            $participant = $this->participantRepository->find(Uuid::fromString($data['participant_id']));
            if ($participant === null) {
                return $this->json(['error' => 'Participant non trouvé'], Response::HTTP_NOT_FOUND);
            }

            $annotation = $this->annotationService->resolve($annotation, $participant);

            return $this->json($this->annotationService->serialize($annotation));
        } catch (\InvalidArgumentException $e) {
            return $this->json(['error' => $e->getMessage()], Response::HTTP_BAD_REQUEST);
        }
    }

    #[Route('/{id}/replies', name: 'reply', methods: ['POST'])]
    public function reply(string $id, Request $request): JsonResponse
    {
        try {
            $annotation = $this->annotationRepository->find(Uuid::fromString($id));

            if ($annotation === null) {
                return $this->json(['error' => 'Annotation non trouvée'], Response::HTTP_NOT_FOUND);
            }

            $data = $request->toArray();

            if (empty($data['participant_id'])) {
                return $this->json(['error' => 'participant_id est requis'], Response::HTTP_BAD_REQUEST);
            }

            if (empty($data['content'])) {
                return $this->json(['error' => 'Le contenu est requis'], Response::HTTP_BAD_REQUEST);
            }

            $participant = $this->participantRepository->find(Uuid::fromString($data['participant_id']));
            if ($participant === null) {
                return $this->json(['error' => 'Participant non trouvé'], Response::HTTP_NOT_FOUND);
            }

            $reply = $this->annotationService->createReply($annotation, $data['content'], $participant);

            return $this->json($this->annotationService->serialize($reply), Response::HTTP_CREATED);
        } catch (\InvalidArgumentException $e) {
            return $this->json(['error' => $e->getMessage()], Response::HTTP_BAD_REQUEST);
        }
    }
}

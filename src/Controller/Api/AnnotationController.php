<?php

namespace App\Controller\Api;

use App\Application\Command\Annotation\CreateAnnotationCommand;
use App\Application\Command\Annotation\CreateAnnotationHandler;
use App\Application\Command\Annotation\RespondAnnotationCommand;
use App\Application\Command\Annotation\RespondAnnotationHandler;
use App\Application\Command\Annotation\ResolveAnnotationCommand;
use App\Application\Command\Annotation\ResolveAnnotationHandler;
use App\Application\Command\Annotation\UpdateAnnotationCommand;
use App\Application\Command\Annotation\UpdateAnnotationHandler;
use App\Application\DTO\Request\CreateAnnotationRequest;
use App\Application\Query\Annotation\GetAnnotationHandler;
use App\Application\Query\Annotation\GetAnnotationQuery;
use App\Domain\Collaboration\Exception\AnnotationNotFoundException;
use App\Domain\Collaboration\Exception\CannotReplyToReplyException;
use App\Domain\Document\Exception\DocumentNotFoundException;
use App\Domain\Session\Exception\ParticipantNotFoundException;
use App\Entity\Annotation;
use App\Repository\AnnotationRepository;
use App\Repository\ParticipantRepository;
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
        private readonly CreateAnnotationHandler $createAnnotationHandler,
        private readonly GetAnnotationHandler $getAnnotationHandler,
        private readonly UpdateAnnotationHandler $updateAnnotationHandler,
        private readonly ResolveAnnotationHandler $resolveAnnotationHandler,
        private readonly RespondAnnotationHandler $respondAnnotationHandler,
        private readonly AnnotationRepository $annotationRepository,
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

        $type = $data['type'] ?? Annotation::TYPE_COMMENT;
        if (!in_array($type, Annotation::TYPES)) {
            return $this->json(['error' => 'Type d\'annotation invalide'], Response::HTTP_BAD_REQUEST);
        }

        try {
            $command = new CreateAnnotationCommand(
                documentId: $data['document_id'],
                authorParticipantId: $data['participant_id'],
                content: $data['content'],
                type: $type,
                anchor: $data['anchor'] ?? null,
            );

            $annotation = ($this->createAnnotationHandler)($command);

            return $this->json($annotation->toArray(), Response::HTTP_CREATED);
        } catch (DocumentNotFoundException $e) {
            return $this->json(['error' => 'Document non trouvé'], Response::HTTP_NOT_FOUND);
        } catch (ParticipantNotFoundException $e) {
            return $this->json(['error' => 'Participant non trouvé'], Response::HTTP_NOT_FOUND);
        } catch (\InvalidArgumentException $e) {
            return $this->json(['error' => $e->getMessage()], Response::HTTP_BAD_REQUEST);
        }
    }

    #[Route('/{id}', name: 'show', methods: ['GET'])]
    public function show(string $id): JsonResponse
    {
        try {
            $query = new GetAnnotationQuery(
                annotationId: $id,
                includeReplies: true,
            );

            $annotation = ($this->getAnnotationHandler)($query);

            return $this->json($annotation->toArray());
        } catch (AnnotationNotFoundException $e) {
            return $this->json(['error' => 'Annotation non trouvée'], Response::HTTP_NOT_FOUND);
        } catch (\InvalidArgumentException $e) {
            return $this->json(['error' => 'ID invalide'], Response::HTTP_BAD_REQUEST);
        }
    }

    #[Route('/{id}', name: 'update', methods: ['PUT', 'PATCH'])]
    public function update(string $id, Request $request): JsonResponse
    {
        try {
            $data = $request->toArray();

            $command = new UpdateAnnotationCommand(
                annotationId: $id,
                content: $data['content'] ?? null,
                status: $data['status'] ?? null,
            );

            $annotation = ($this->updateAnnotationHandler)($command);

            return $this->json($annotation->toArray());
        } catch (AnnotationNotFoundException $e) {
            return $this->json(['error' => 'Annotation non trouvée'], Response::HTTP_NOT_FOUND);
        } catch (\InvalidArgumentException $e) {
            return $this->json(['error' => $e->getMessage()], Response::HTTP_BAD_REQUEST);
        }
    }

    #[Route('/{id}/resolve', name: 'resolve', methods: ['POST'])]
    public function resolve(string $id, Request $request): Response
    {
        try {
            // Support both JSON and form data
            $contentType = $request->headers->get('Content-Type', '');
            if (str_contains($contentType, 'application/json')) {
                $data = $request->toArray();
            } else {
                $data = $request->request->all();
            }

            if (empty($data['participant_id'])) {
                return $this->json(['error' => 'participant_id est requis'], Response::HTTP_BAD_REQUEST);
            }

            $command = new ResolveAnnotationCommand(
                annotationId: $id,
                resolvedByParticipantId: $data['participant_id'],
            );

            $annotation = ($this->resolveAnnotationHandler)($command);

            // Return HTML for HTMX requests, JSON otherwise
            if ($request->headers->has('HX-Request')) {
                // Need to get the entity for Twig template
                $annotationEntity = $this->annotationRepository->find(Uuid::fromString($id));
                $participant = $this->participantRepository->find(Uuid::fromString($data['participant_id']));
                return $this->render('annotation/_item.html.twig', [
                    'annotation' => $annotationEntity,
                    'participant' => $participant,
                ]);
            }

            return $this->json($annotation->toArray());
        } catch (AnnotationNotFoundException $e) {
            return $this->json(['error' => 'Annotation non trouvée'], Response::HTTP_NOT_FOUND);
        } catch (ParticipantNotFoundException $e) {
            return $this->json(['error' => 'Participant non trouvé'], Response::HTTP_NOT_FOUND);
        } catch (\InvalidArgumentException $e) {
            return $this->json(['error' => $e->getMessage()], Response::HTTP_BAD_REQUEST);
        }
    }

    #[Route('/{id}/replies', name: 'reply', methods: ['POST'])]
    public function reply(string $id, Request $request): Response
    {
        try {
            // Support both JSON and form data
            $contentType = $request->headers->get('Content-Type', '');
            if (str_contains($contentType, 'application/json')) {
                $data = $request->toArray();
            } else {
                $data = $request->request->all();
            }

            if (empty($data['participant_id'])) {
                return $this->json(['error' => 'participant_id est requis'], Response::HTTP_BAD_REQUEST);
            }

            if (empty($data['content'])) {
                return $this->json(['error' => 'Le contenu est requis'], Response::HTTP_BAD_REQUEST);
            }

            $command = new RespondAnnotationCommand(
                annotationId: $id,
                authorParticipantId: $data['participant_id'],
                content: $data['content'],
                markAsResolved: $data['mark_as_resolved'] ?? false,
            );

            $reply = ($this->respondAnnotationHandler)($command);

            // Return HTML for HTMX requests, JSON otherwise
            if ($request->headers->has('HX-Request')) {
                // Refresh the parent annotation to include the new reply
                $annotation = $this->annotationRepository->find(Uuid::fromString($id));
                $participant = $this->participantRepository->find(Uuid::fromString($data['participant_id']));
                return $this->render('annotation/_item.html.twig', [
                    'annotation' => $annotation,
                    'participant' => $participant,
                ]);
            }

            return $this->json($reply->toArray(), Response::HTTP_CREATED);
        } catch (AnnotationNotFoundException $e) {
            return $this->json(['error' => 'Annotation non trouvée'], Response::HTTP_NOT_FOUND);
        } catch (ParticipantNotFoundException $e) {
            return $this->json(['error' => 'Participant non trouvé'], Response::HTTP_NOT_FOUND);
        } catch (CannotReplyToReplyException $e) {
            return $this->json(['error' => $e->getMessage()], Response::HTTP_BAD_REQUEST);
        } catch (\InvalidArgumentException $e) {
            return $this->json(['error' => $e->getMessage()], Response::HTTP_BAD_REQUEST);
        }
    }
}

<?php

namespace App\Controller\Api;

use App\Entity\Decision;
use App\Repository\DecisionRepository;
use App\Repository\DocumentRepository;
use App\Repository\ParticipantRepository;
use App\Repository\SessionRepository;
use App\Service\Decision\DecisionService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Uid\Uuid;

#[Route('/api/decisions', name: 'api_decisions_')]
class DecisionController extends AbstractController
{
    public function __construct(
        private readonly DecisionService $decisionService,
        private readonly DecisionRepository $decisionRepository,
        private readonly SessionRepository $sessionRepository,
        private readonly DocumentRepository $documentRepository,
        private readonly ParticipantRepository $participantRepository,
    ) {}

    #[Route('', name: 'create', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        $data = $request->toArray();

        if (empty($data['session_id'])) {
            return $this->json(['error' => 'session_id est requis'], Response::HTTP_BAD_REQUEST);
        }

        if (empty($data['title'])) {
            return $this->json(['error' => 'Le titre est requis'], Response::HTTP_BAD_REQUEST);
        }

        if (empty($data['options']) || !is_array($data['options']) || count($data['options']) < 2) {
            return $this->json(['error' => 'Au moins 2 options sont requises'], Response::HTTP_BAD_REQUEST);
        }

        // Normaliser les options (accepter chaînes ou objets)
        $normalizedOptions = [];
        foreach ($data['options'] as $option) {
            if (is_string($option)) {
                $normalizedOptions[] = ['label' => $option];
            } elseif (is_array($option)) {
                $normalizedOptions[] = [
                    'label' => $option['label'] ?? $option['text'] ?? '',
                    'description' => $option['description'] ?? null,
                ];
            }
        }

        try {
            $session = $this->sessionRepository->find(Uuid::fromString($data['session_id']));
            if ($session === null) {
                return $this->json(['error' => 'Session non trouvée'], Response::HTTP_NOT_FOUND);
            }

            $linkedDocument = null;
            $documentId = $data['linked_document_id'] ?? $data['document_id'] ?? null;
            if (!empty($documentId)) {
                $linkedDocument = $this->documentRepository->find(Uuid::fromString($documentId));
            }

            $decision = $this->decisionService->create(
                $session,
                $data['title'],
                $normalizedOptions,
                $data['description'] ?? null,
                $linkedDocument
            );

            return $this->json($this->decisionService->serialize($decision), Response::HTTP_CREATED);
        } catch (\InvalidArgumentException $e) {
            return $this->json(['error' => $e->getMessage()], Response::HTTP_BAD_REQUEST);
        }
    }

    #[Route('/session/{sessionId}', name: 'list', methods: ['GET'])]
    public function list(string $sessionId, Request $request): JsonResponse
    {
        try {
            $session = $this->sessionRepository->find(Uuid::fromString($sessionId));
            if ($session === null) {
                return $this->json(['error' => 'Session non trouvée'], Response::HTTP_NOT_FOUND);
            }

            $criteria = ['session' => $session];

            $documentId = $request->query->get('document_id');
            if ($documentId) {
                $document = $this->documentRepository->find(Uuid::fromString($documentId));
                if ($document) {
                    $criteria['linkedDocument'] = $document;
                }
            }

            $status = $request->query->get('status');
            if ($status) {
                $criteria['status'] = $status;
            }

            $decisions = $this->decisionRepository->findBy($criteria, ['createdAt' => 'DESC']);

            return $this->json(array_map(
                fn($d) => $this->decisionService->serialize($d),
                $decisions
            ));
        } catch (\InvalidArgumentException $e) {
            return $this->json(['error' => 'ID invalide'], Response::HTTP_BAD_REQUEST);
        }
    }

    #[Route('/session/{sessionId}/arbitrations', name: 'arbitrations', methods: ['GET'])]
    public function getArbitrations(string $sessionId): JsonResponse
    {
        try {
            $session = $this->sessionRepository->find(Uuid::fromString($sessionId));
            if ($session === null) {
                return $this->json(['error' => 'Session non trouvée'], Response::HTTP_NOT_FOUND);
            }

            $decisions = $this->decisionRepository->findBy([
                'session' => $session,
                'status' => Decision::STATUS_VALIDE,
            ], ['updatedAt' => 'DESC']);

            $arbitrations = [];
            foreach ($decisions as $decision) {
                $selectedOption = null;
                $selectedOptionId = $decision->getSelectedOptionId()?->toString();
                foreach ($decision->getOptions() as $option) {
                    if ($option['id'] === $selectedOptionId) {
                        $selectedOption = $option;
                        break;
                    }
                }

                $arbitrations[] = [
                    'id' => $decision->getId()->toString(),
                    'title' => $decision->getTitle(),
                    'description' => $decision->getDescription(),
                    'selected_option' => $selectedOption,
                    'document' => $decision->getLinkedDocument() ? [
                        'id' => $decision->getLinkedDocument()->getId()->toString(),
                        'title' => $decision->getLinkedDocument()->getTitle(),
                        'slug' => $decision->getLinkedDocument()->getSlug(),
                    ] : null,
                    'validated_at' => $decision->getUpdatedAt()->format(\DateTimeInterface::ATOM),
                    'vote_count' => $decision->getVotes()->count(),
                ];
            }

            return $this->json($arbitrations);
        } catch (\InvalidArgumentException $e) {
            return $this->json(['error' => 'ID invalide'], Response::HTTP_BAD_REQUEST);
        }
    }

    #[Route('/{id}', name: 'show', methods: ['GET'])]
    public function show(string $id): JsonResponse
    {
        try {
            $decision = $this->decisionRepository->findByIdWithVotes($id);

            if ($decision === null) {
                return $this->json(['error' => 'Décision non trouvée'], Response::HTTP_NOT_FOUND);
            }

            return $this->json($this->decisionService->serialize($decision));
        } catch (\InvalidArgumentException $e) {
            return $this->json(['error' => 'ID invalide'], Response::HTTP_BAD_REQUEST);
        }
    }

    #[Route('/{id}/vote', name: 'vote', methods: ['POST'])]
    public function vote(string $id, Request $request): JsonResponse
    {
        try {
            $decision = $this->decisionRepository->find(Uuid::fromString($id));

            if ($decision === null) {
                return $this->json(['error' => 'Décision non trouvée'], Response::HTTP_NOT_FOUND);
            }

            $data = $request->toArray();

            if (empty($data['participant_id'])) {
                return $this->json(['error' => 'participant_id est requis'], Response::HTTP_BAD_REQUEST);
            }

            if (empty($data['option_id'])) {
                return $this->json(['error' => 'option_id est requis'], Response::HTTP_BAD_REQUEST);
            }

            $participant = $this->participantRepository->find(Uuid::fromString($data['participant_id']));
            if ($participant === null) {
                return $this->json(['error' => 'Participant non trouvé'], Response::HTTP_NOT_FOUND);
            }

            $validOptionIds = array_column($decision->getOptions(), 'id');
            if (!in_array($data['option_id'], $validOptionIds)) {
                return $this->json(['error' => 'Option invalide'], Response::HTTP_BAD_REQUEST);
            }

            $vote = $this->decisionService->vote(
                $decision,
                $participant,
                $data['option_id'],
                $data['comment'] ?? null
            );

            return $this->json([
                'vote' => [
                    'id' => $vote->getId()->toString(),
                    'option_id' => $vote->getOptionId()->toString(),
                    'comment' => $vote->getComment(),
                ],
                'stats' => $decision->getVoteStats(),
            ]);
        } catch (\LogicException $e) {
            return $this->json(['error' => $e->getMessage()], Response::HTTP_CONFLICT);
        } catch (\InvalidArgumentException $e) {
            return $this->json(['error' => $e->getMessage()], Response::HTTP_BAD_REQUEST);
        }
    }

    #[Route('/{id}/status', name: 'update_status', methods: ['PATCH'])]
    public function updateStatus(string $id, Request $request): JsonResponse
    {
        try {
            $decision = $this->decisionRepository->find(Uuid::fromString($id));

            if ($decision === null) {
                return $this->json(['error' => 'Décision non trouvée'], Response::HTTP_NOT_FOUND);
            }

            $data = $request->toArray();

            if (empty($data['status']) || !in_array($data['status'], Decision::STATUSES)) {
                return $this->json(['error' => 'Statut invalide'], Response::HTTP_BAD_REQUEST);
            }

            $decision = $this->decisionService->updateStatus($decision, $data['status']);

            return $this->json($this->decisionService->serialize($decision));
        } catch (\LogicException $e) {
            return $this->json(['error' => $e->getMessage()], Response::HTTP_CONFLICT);
        } catch (\InvalidArgumentException $e) {
            return $this->json(['error' => $e->getMessage()], Response::HTTP_BAD_REQUEST);
        }
    }

    #[Route('/{id}/validate', name: 'validate', methods: ['POST'])]
    public function validate(string $id, Request $request): JsonResponse
    {
        try {
            $decision = $this->decisionRepository->find(Uuid::fromString($id));

            if ($decision === null) {
                return $this->json(['error' => 'Décision non trouvée'], Response::HTTP_NOT_FOUND);
            }

            $data = $request->toArray();

            if (empty($data['selected_option_id'])) {
                return $this->json(['error' => 'selected_option_id est requis'], Response::HTTP_BAD_REQUEST);
            }

            $validOptionIds = array_column($decision->getOptions(), 'id');
            if (!in_array($data['selected_option_id'], $validOptionIds)) {
                return $this->json(['error' => 'Option invalide'], Response::HTTP_BAD_REQUEST);
            }

            $decision = $this->decisionService->validate($decision, $data['selected_option_id']);

            return $this->json($this->decisionService->serialize($decision));
        } catch (\LogicException $e) {
            return $this->json(['error' => $e->getMessage()], Response::HTTP_CONFLICT);
        } catch (\InvalidArgumentException $e) {
            return $this->json(['error' => $e->getMessage()], Response::HTTP_BAD_REQUEST);
        }
    }

    #[Route('/{id}/postpone', name: 'postpone', methods: ['POST'])]
    public function postpone(string $id): JsonResponse
    {
        try {
            $decision = $this->decisionRepository->find(Uuid::fromString($id));

            if ($decision === null) {
                return $this->json(['error' => 'Décision non trouvée'], Response::HTTP_NOT_FOUND);
            }

            $decision = $this->decisionService->postpone($decision);

            return $this->json($this->decisionService->serialize($decision));
        } catch (\InvalidArgumentException $e) {
            return $this->json(['error' => $e->getMessage()], Response::HTTP_BAD_REQUEST);
        }
    }

    #[Route('/{id}', name: 'delete', methods: ['DELETE'])]
    public function delete(string $id): JsonResponse
    {
        try {
            $decision = $this->decisionRepository->find(Uuid::fromString($id));

            if ($decision === null) {
                return $this->json(['error' => 'Décision non trouvée'], Response::HTTP_NOT_FOUND);
            }

            $this->decisionService->delete($decision);

            return $this->json(['success' => true], Response::HTTP_OK);
        } catch (\InvalidArgumentException $e) {
            return $this->json(['error' => $e->getMessage()], Response::HTTP_BAD_REQUEST);
        }
    }
}

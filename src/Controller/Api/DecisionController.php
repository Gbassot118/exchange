<?php

namespace App\Controller\Api;

use App\Application\Command\Decision\CreateDecisionCommand;
use App\Application\Command\Decision\CreateDecisionHandler;
use App\Application\Command\Decision\DeleteDecisionCommand;
use App\Application\Command\Decision\DeleteDecisionHandler;
use App\Application\Command\Decision\PostponeDecisionCommand;
use App\Application\Command\Decision\PostponeDecisionHandler;
use App\Application\Command\Decision\ValidateDecisionCommand;
use App\Application\Command\Decision\ValidateDecisionHandler;
use App\Application\Command\Decision\VoteCommand;
use App\Application\Command\Decision\VoteHandler;
use App\Application\Query\Decision\GetArbitrationsHandler;
use App\Application\Query\Decision\GetArbitrationsQuery;
use App\Application\Query\Decision\GetDecisionHandler;
use App\Application\Query\Decision\GetDecisionQuery;
use App\Application\Query\Decision\ListDecisionsHandler;
use App\Application\Query\Decision\ListDecisionsQuery;
use App\Domain\Decision\Exception\DecisionLockedException;
use App\Domain\Decision\Exception\DecisionNotFoundException;
use App\Domain\Session\Exception\ParticipantNotFoundException;
use App\Domain\Session\Exception\SessionNotFoundException;
use App\Entity\Decision;
use App\Repository\DecisionRepository;
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
        private readonly CreateDecisionHandler $createDecisionHandler,
        private readonly GetDecisionHandler $getDecisionHandler,
        private readonly ListDecisionsHandler $listDecisionsHandler,
        private readonly GetArbitrationsHandler $getArbitrationsHandler,
        private readonly VoteHandler $voteHandler,
        private readonly ValidateDecisionHandler $validateDecisionHandler,
        private readonly PostponeDecisionHandler $postponeDecisionHandler,
        private readonly DeleteDecisionHandler $deleteDecisionHandler,
        private readonly DecisionRepository $decisionRepository,
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
            $command = new CreateDecisionCommand(
                sessionId: $data['session_id'],
                title: $data['title'],
                options: $normalizedOptions,
                description: $data['description'] ?? null,
                linkedDocumentId: $data['linked_document_id'] ?? $data['document_id'] ?? null,
            );

            $decision = ($this->createDecisionHandler)($command);

            return $this->json($decision->toArray(), Response::HTTP_CREATED);
        } catch (SessionNotFoundException $e) {
            return $this->json(['error' => 'Session non trouvée'], Response::HTTP_NOT_FOUND);
        } catch (\InvalidArgumentException $e) {
            return $this->json(['error' => $e->getMessage()], Response::HTTP_BAD_REQUEST);
        }
    }

    #[Route('/session/{sessionId}', name: 'list', methods: ['GET'])]
    public function list(string $sessionId, Request $request): JsonResponse
    {
        try {
            $status = $request->query->get('status');

            $query = new ListDecisionsQuery(
                sessionId: $sessionId,
                status: $status,
            );

            $decisions = ($this->listDecisionsHandler)($query);

            return $this->json(array_map(fn($d) => $d->toArray(), $decisions));
        } catch (SessionNotFoundException $e) {
            return $this->json(['error' => 'Session non trouvée'], Response::HTTP_NOT_FOUND);
        } catch (\InvalidArgumentException $e) {
            return $this->json(['error' => 'ID invalide'], Response::HTTP_BAD_REQUEST);
        }
    }

    #[Route('/session/{sessionId}/arbitrations', name: 'arbitrations', methods: ['GET'])]
    public function getArbitrations(string $sessionId): JsonResponse
    {
        try {
            $query = new GetArbitrationsQuery(sessionId: $sessionId);
            $arbitrations = ($this->getArbitrationsHandler)($query);

            return $this->json($arbitrations);
        } catch (SessionNotFoundException $e) {
            return $this->json(['error' => 'Session non trouvée'], Response::HTTP_NOT_FOUND);
        } catch (\InvalidArgumentException $e) {
            return $this->json(['error' => 'ID invalide'], Response::HTTP_BAD_REQUEST);
        }
    }

    #[Route('/{id}', name: 'show', methods: ['GET'])]
    public function show(string $id): JsonResponse
    {
        try {
            $query = new GetDecisionQuery(
                decisionId: $id,
                includeVotes: true,
            );

            $decision = ($this->getDecisionHandler)($query);

            return $this->json($decision->toArray());
        } catch (DecisionNotFoundException $e) {
            return $this->json(['error' => 'Décision non trouvée'], Response::HTTP_NOT_FOUND);
        } catch (\InvalidArgumentException $e) {
            return $this->json(['error' => 'ID invalide'], Response::HTTP_BAD_REQUEST);
        }
    }

    #[Route('/{id}/vote', name: 'vote', methods: ['POST'])]
    public function vote(string $id, Request $request): JsonResponse
    {
        try {
            $data = $request->toArray();

            if (empty($data['participant_id'])) {
                return $this->json(['error' => 'participant_id est requis'], Response::HTTP_BAD_REQUEST);
            }

            if (empty($data['option_id'])) {
                return $this->json(['error' => 'option_id est requis'], Response::HTTP_BAD_REQUEST);
            }

            // Validate option exists
            $decision = $this->decisionRepository->find(Uuid::fromString($id));
            if ($decision === null) {
                return $this->json(['error' => 'Décision non trouvée'], Response::HTTP_NOT_FOUND);
            }

            $validOptionIds = array_column($decision->getOptions(), 'id');
            if (!in_array($data['option_id'], $validOptionIds)) {
                return $this->json(['error' => 'Option invalide'], Response::HTTP_BAD_REQUEST);
            }

            $command = new VoteCommand(
                decisionId: $id,
                participantId: $data['participant_id'],
                optionId: $data['option_id'],
                comment: $data['comment'] ?? null,
            );

            $vote = ($this->voteHandler)($command);

            // Refresh decision to get updated stats
            $decision = $this->decisionRepository->find(Uuid::fromString($id));

            return $this->json([
                'vote' => $vote->toArray(),
                'stats' => $decision->getVoteStats(),
            ]);
        } catch (DecisionNotFoundException $e) {
            return $this->json(['error' => 'Décision non trouvée'], Response::HTTP_NOT_FOUND);
        } catch (ParticipantNotFoundException $e) {
            return $this->json(['error' => 'Participant non trouvé'], Response::HTTP_NOT_FOUND);
        } catch (DecisionLockedException $e) {
            return $this->json(['error' => $e->getMessage()], Response::HTTP_CONFLICT);
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
            $data = $request->toArray();

            if (empty($data['status']) || !in_array($data['status'], Decision::STATUSES)) {
                return $this->json(['error' => 'Statut invalide'], Response::HTTP_BAD_REQUEST);
            }

            // For now, use validate/postpone handlers based on status
            if ($data['status'] === Decision::STATUS_VALIDE && !empty($data['selected_option_id'])) {
                $command = new ValidateDecisionCommand(
                    decisionId: $id,
                    selectedOptionId: $data['selected_option_id'],
                );
                $decision = ($this->validateDecisionHandler)($command);
            } elseif ($data['status'] === Decision::STATUS_REPORTE) {
                $command = new PostponeDecisionCommand(decisionId: $id);
                $decision = ($this->postponeDecisionHandler)($command);
            } else {
                // Direct status update not supported yet
                return $this->json(['error' => 'Changement de statut non supporté'], Response::HTTP_BAD_REQUEST);
            }

            return $this->json($decision->toArray());
        } catch (DecisionNotFoundException $e) {
            return $this->json(['error' => 'Décision non trouvée'], Response::HTTP_NOT_FOUND);
        } catch (DecisionLockedException $e) {
            return $this->json(['error' => $e->getMessage()], Response::HTTP_CONFLICT);
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
            $data = $request->toArray();

            if (empty($data['selected_option_id'])) {
                return $this->json(['error' => 'selected_option_id est requis'], Response::HTTP_BAD_REQUEST);
            }

            // Validate option exists
            $decisionEntity = $this->decisionRepository->find(Uuid::fromString($id));
            if ($decisionEntity === null) {
                return $this->json(['error' => 'Décision non trouvée'], Response::HTTP_NOT_FOUND);
            }

            $validOptionIds = array_column($decisionEntity->getOptions(), 'id');
            if (!in_array($data['selected_option_id'], $validOptionIds)) {
                return $this->json(['error' => 'Option invalide'], Response::HTTP_BAD_REQUEST);
            }

            $command = new ValidateDecisionCommand(
                decisionId: $id,
                selectedOptionId: $data['selected_option_id'],
            );

            $decision = ($this->validateDecisionHandler)($command);

            return $this->json($decision->toArray());
        } catch (DecisionNotFoundException $e) {
            return $this->json(['error' => 'Décision non trouvée'], Response::HTTP_NOT_FOUND);
        } catch (DecisionLockedException $e) {
            return $this->json(['error' => $e->getMessage()], Response::HTTP_CONFLICT);
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
            $command = new PostponeDecisionCommand(decisionId: $id);
            $decision = ($this->postponeDecisionHandler)($command);

            return $this->json($decision->toArray());
        } catch (DecisionNotFoundException $e) {
            return $this->json(['error' => 'Décision non trouvée'], Response::HTTP_NOT_FOUND);
        } catch (\InvalidArgumentException $e) {
            return $this->json(['error' => $e->getMessage()], Response::HTTP_BAD_REQUEST);
        }
    }

    #[Route('/{id}', name: 'delete', methods: ['DELETE'])]
    public function delete(string $id): JsonResponse
    {
        try {
            $command = new DeleteDecisionCommand(decisionId: $id);
            ($this->deleteDecisionHandler)($command);

            return $this->json(['success' => true], Response::HTTP_OK);
        } catch (DecisionNotFoundException $e) {
            return $this->json(['error' => 'Décision non trouvée'], Response::HTTP_NOT_FOUND);
        } catch (\InvalidArgumentException $e) {
            return $this->json(['error' => $e->getMessage()], Response::HTTP_BAD_REQUEST);
        }
    }
}

<?php

namespace App\Controller\Api;

use App\Application\Command\Estimation\CreateEstimationCommand;
use App\Application\Command\Estimation\CreateEstimationHandler;
use App\Application\Command\Estimation\RevealEstimationCommand;
use App\Application\Command\Estimation\RevealEstimationHandler;
use App\Application\Command\Estimation\VoteEstimationCommand;
use App\Application\Command\Estimation\VoteEstimationHandler;
use App\Application\Query\Estimation\GetEstimationHandler;
use App\Application\Query\Estimation\GetEstimationQuery;
use App\Application\Query\Estimation\ListEstimationsHandler;
use App\Application\Query\Estimation\ListEstimationsQuery;
use App\Domain\Estimation\Exception\EstimationAlreadyRevealedException;
use App\Domain\Estimation\Exception\EstimationNotFoundException;
use App\Domain\Estimation\Exception\InvalidFibonacciValueException;
use App\Domain\Estimation\ValueObject\FibonacciValue;
use App\Domain\Session\Exception\ParticipantNotFoundException;
use App\Domain\Session\Exception\SessionNotFoundException;
use App\Entity\Estimation;
use App\Repository\EstimationRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Uid\Uuid;

#[Route('/api/estimations', name: 'api_estimations_')]
class EstimationController extends AbstractController
{
    public function __construct(
        private readonly CreateEstimationHandler $createEstimationHandler,
        private readonly GetEstimationHandler $getEstimationHandler,
        private readonly ListEstimationsHandler $listEstimationsHandler,
        private readonly VoteEstimationHandler $voteEstimationHandler,
        private readonly RevealEstimationHandler $revealEstimationHandler,
        private readonly EstimationRepository $estimationRepository,
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

        try {
            $command = new CreateEstimationCommand(
                sessionId: $data['session_id'],
                title: $data['title'],
                description: $data['description'] ?? null,
                linkedDocumentId: $data['linked_document_id'] ?? $data['document_id'] ?? null,
            );

            $estimation = ($this->createEstimationHandler)($command);

            return $this->json($estimation->toArray(), Response::HTTP_CREATED);
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
            $documentId = $request->query->get('document_id');

            $query = new ListEstimationsQuery(
                sessionId: $sessionId,
                status: $status,
                documentId: $documentId,
            );

            $estimations = ($this->listEstimationsHandler)($query);

            return $this->json(array_map(fn($e) => $e->toArray(), $estimations));
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
            $query = new GetEstimationQuery(
                estimationId: $id,
                includeVotes: true,
            );

            $estimation = ($this->getEstimationHandler)($query);

            return $this->json($estimation->toArray());
        } catch (EstimationNotFoundException $e) {
            return $this->json(['error' => 'Chiffrage non trouvé'], Response::HTTP_NOT_FOUND);
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

            if (!isset($data['value'])) {
                return $this->json(['error' => 'value est requis'], Response::HTTP_BAD_REQUEST);
            }

            $value = (string) $data['value'];
            if (!FibonacciValue::isValid($value)) {
                return $this->json([
                    'error' => 'Valeur Fibonacci invalide',
                    'valid_values' => FibonacciValue::VALUES,
                ], Response::HTTP_BAD_REQUEST);
            }

            $command = new VoteEstimationCommand(
                estimationId: $id,
                participantId: $data['participant_id'],
                value: $value,
            );

            $estimation = ($this->voteEstimationHandler)($command);

            return $this->json([
                'estimation' => $estimation->toArray(),
                'voted' => true,
            ]);
        } catch (EstimationNotFoundException $e) {
            return $this->json(['error' => 'Chiffrage non trouvé'], Response::HTTP_NOT_FOUND);
        } catch (ParticipantNotFoundException $e) {
            return $this->json(['error' => 'Participant non trouvé'], Response::HTTP_NOT_FOUND);
        } catch (EstimationAlreadyRevealedException $e) {
            return $this->json(['error' => $e->getMessage()], Response::HTTP_CONFLICT);
        } catch (InvalidFibonacciValueException $e) {
            return $this->json(['error' => $e->getMessage()], Response::HTTP_BAD_REQUEST);
        } catch (\InvalidArgumentException $e) {
            return $this->json(['error' => $e->getMessage()], Response::HTTP_BAD_REQUEST);
        }
    }

    #[Route('/{id}/reveal', name: 'reveal', methods: ['POST'])]
    public function reveal(string $id): JsonResponse
    {
        try {
            $command = new RevealEstimationCommand(estimationId: $id);
            $estimation = ($this->revealEstimationHandler)($command);

            return $this->json($estimation->toArray());
        } catch (EstimationNotFoundException $e) {
            return $this->json(['error' => 'Chiffrage non trouvé'], Response::HTTP_NOT_FOUND);
        } catch (EstimationAlreadyRevealedException $e) {
            return $this->json(['error' => $e->getMessage()], Response::HTTP_CONFLICT);
        } catch (\InvalidArgumentException $e) {
            return $this->json(['error' => $e->getMessage()], Response::HTTP_BAD_REQUEST);
        }
    }

    #[Route('/{id}', name: 'delete', methods: ['DELETE'])]
    public function delete(string $id): JsonResponse
    {
        try {
            $estimation = $this->estimationRepository->find(Uuid::fromString($id));
            if ($estimation === null) {
                return $this->json(['error' => 'Chiffrage non trouvé'], Response::HTTP_NOT_FOUND);
            }

            $this->estimationRepository->remove($estimation);

            return $this->json(['success' => true], Response::HTTP_OK);
        } catch (\InvalidArgumentException $e) {
            return $this->json(['error' => $e->getMessage()], Response::HTTP_BAD_REQUEST);
        }
    }

    #[Route('/fibonacci-values', name: 'fibonacci_values', methods: ['GET'])]
    public function fibonacciValues(): JsonResponse
    {
        return $this->json([
            'values' => FibonacciValue::VALUES,
        ]);
    }
}

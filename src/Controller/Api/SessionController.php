<?php

namespace App\Controller\Api;

use App\Application\Command\Session\CreateSessionCommand;
use App\Application\Command\Session\CreateSessionHandler;
use App\Application\Command\Session\JoinSessionCommand;
use App\Application\Command\Session\JoinSessionHandler;
use App\Application\Command\Session\UpdateSessionStatusCommand;
use App\Application\Command\Session\UpdateSessionStatusHandler;
use App\Application\DTO\Request\CreateSessionRequest;
use App\Application\DTO\Request\JoinSessionRequest;
use App\Application\DTO\Request\UpdateSessionStatusRequest;
use App\Application\Query\Session\GetSessionQuery;
use App\Application\Query\Session\GetSessionHandler;
use App\Application\Query\Session\ListSessionsQuery;
use App\Application\Query\Session\ListSessionsHandler;
use App\Domain\Session\Exception\InvalidSessionStatusTransitionException;
use App\Domain\Session\Exception\SessionArchivedException;
use App\Domain\Session\Exception\SessionNotFoundException;
use App\Domain\Session\ValueObject\SessionStatus;
use App\Repository\ParticipantRepository;
use App\Repository\SessionRepository;
use App\Service\Session\SessionService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Uid\Uuid;

#[Route('/api/sessions', name: 'api_sessions_')]
class SessionController extends AbstractController
{
    public function __construct(
        private readonly CreateSessionHandler $createSessionHandler,
        private readonly JoinSessionHandler $joinSessionHandler,
        private readonly UpdateSessionStatusHandler $updateStatusHandler,
        private readonly GetSessionHandler $getSessionHandler,
        private readonly ListSessionsHandler $listSessionsHandler,
        private readonly SessionService $sessionService,
        private readonly SessionRepository $sessionRepository,
        private readonly ParticipantRepository $participantRepository,
    ) {}

    #[Route('', name: 'create', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        $data = $request->toArray();
        $dto = CreateSessionRequest::fromArray($data);

        if (empty($dto->title)) {
            return $this->json(['error' => 'Le titre est requis'], Response::HTTP_BAD_REQUEST);
        }

        if (empty($dto->creatorPseudo)) {
            return $this->json(['error' => 'Le pseudo du créateur est requis'], Response::HTTP_BAD_REQUEST);
        }

        $command = new CreateSessionCommand(
            title: $dto->title,
            description: $dto->description,
            creatorPseudo: $dto->creatorPseudo,
            isAgent: $dto->isAgent,
        );

        $result = ($this->createSessionHandler)($command);

        return $this->json([
            'session' => $result['session']->toArray(),
            'participant' => $result['participant']->toArray(),
        ], Response::HTTP_CREATED);
    }

    #[Route('/agent/create', name: 'agent_create', methods: ['POST'])]
    public function agentCreate(Request $request): JsonResponse
    {
        $data = $request->toArray();

        if (empty($data['title'])) {
            return $this->json(['error' => 'Le titre est requis'], Response::HTTP_BAD_REQUEST);
        }

        $agentName = $data['agent_name'] ?? 'Claude Assistant';

        $command = new CreateSessionCommand(
            title: $data['title'],
            description: $data['description'] ?? null,
            creatorPseudo: $agentName,
            isAgent: true,
        );

        $result = ($this->createSessionHandler)($command);
        $session = $result['session'];
        $participant = $result['participant'];

        return $this->json([
            'session' => $session->toArray(),
            'agent' => [
                'participant_id' => $participant->id,
                'pseudo' => $participant->pseudo,
                'color' => $participant->color,
            ],
            'endpoints' => [
                'documents' => '/api/mcp/sessions/' . $session->id . '/documents',
                'status' => '/api/mcp/sessions/' . $session->id . '/status',
                'heartbeat' => '/api/sessions/' . $session->id . '/heartbeat',
            ],
            'invite_url' => '/session/join?code=' . $session->inviteCode,
        ], Response::HTTP_CREATED);
    }

    #[Route('', name: 'list', methods: ['GET'])]
    public function list(): JsonResponse
    {
        $query = new ListSessionsQuery(activeOnly: false, limit: 50);
        $sessions = ($this->listSessionsHandler)($query);

        return $this->json([
            'sessions' => array_map(fn($s) => $s->toArray(), $sessions),
        ]);
    }

    #[Route('/{id}', name: 'show', methods: ['GET'])]
    public function show(string $id): JsonResponse
    {
        try {
            $query = new GetSessionQuery(sessionId: $id, includeParticipants: true);
            $session = ($this->getSessionHandler)($query);

            return $this->json($session->toArray());
        } catch (SessionNotFoundException $e) {
            return $this->json(['error' => 'Session non trouvée'], Response::HTTP_NOT_FOUND);
        } catch (\InvalidArgumentException $e) {
            return $this->json(['error' => 'ID de session invalide'], Response::HTTP_BAD_REQUEST);
        }
    }

    #[Route('/join/{inviteCode}', name: 'join', methods: ['POST'])]
    public function join(string $inviteCode, Request $request): JsonResponse
    {
        $data = $request->toArray();
        $dto = JoinSessionRequest::fromArray($data);

        if (empty($dto->pseudo)) {
            return $this->json(['error' => 'Le pseudo est requis'], Response::HTTP_BAD_REQUEST);
        }

        try {
            $command = new JoinSessionCommand(
                inviteCode: $inviteCode,
                pseudo: $dto->pseudo,
                color: $dto->color,
                isAgent: $dto->isAgent,
            );

            $result = ($this->joinSessionHandler)($command);

            return $this->json([
                'session' => $result['session']->toArray(),
                'participant' => $result['participant']->toArray(),
            ]);
        } catch (SessionNotFoundException $e) {
            return $this->json(['error' => 'Code d\'invitation invalide'], Response::HTTP_NOT_FOUND);
        } catch (SessionArchivedException $e) {
            return $this->json(['error' => 'Cette session est archivée'], Response::HTTP_GONE);
        }
    }

    #[Route('/{id}/participants', name: 'participants', methods: ['GET'])]
    public function participants(string $id): JsonResponse
    {
        try {
            $session = $this->sessionRepository->find(Uuid::fromString($id));

            if ($session === null) {
                return $this->json(['error' => 'Session non trouvée'], Response::HTTP_NOT_FOUND);
            }

            $participants = $this->sessionService->getOnlineParticipants($session);

            return $this->json([
                'participants' => array_map(fn($p) => [
                    'id' => $p->getId()->toString(),
                    'pseudo' => $p->getPseudo(),
                    'color' => $p->getColor(),
                    'is_agent' => $p->isAgent(),
                    'current_document_id' => $p->getCurrentDocumentId()?->toString(),
                ], $participants),
            ]);
        } catch (\InvalidArgumentException $e) {
            return $this->json(['error' => 'ID de session invalide'], Response::HTTP_BAD_REQUEST);
        }
    }

    #[Route('/{id}/status', name: 'update_status', methods: ['PATCH'])]
    public function updateStatus(string $id, Request $request): JsonResponse
    {
        try {
            $data = $request->toArray();
            $dto = UpdateSessionStatusRequest::fromArray($data);

            $command = new UpdateSessionStatusCommand(
                sessionId: $id,
                newStatus: $dto->toSessionStatus(),
            );

            $session = ($this->updateStatusHandler)($command);

            return $this->json($session->toArray());
        } catch (SessionNotFoundException $e) {
            return $this->json(['error' => 'Session non trouvée'], Response::HTTP_NOT_FOUND);
        } catch (InvalidSessionStatusTransitionException $e) {
            return $this->json(['error' => $e->getMessage()], Response::HTTP_BAD_REQUEST);
        } catch (\InvalidArgumentException $e) {
            return $this->json(['error' => 'ID de session invalide'], Response::HTTP_BAD_REQUEST);
        }
    }

    #[Route('/{id}/heartbeat', name: 'heartbeat', methods: ['POST'])]
    public function heartbeat(string $id, Request $request): JsonResponse
    {
        $data = $request->toArray();
        $participantId = $data['participant_id'] ?? null;
        $currentDocumentId = $data['current_document_id'] ?? null;

        if (empty($participantId)) {
            return $this->json(['error' => 'participant_id est requis'], Response::HTTP_BAD_REQUEST);
        }

        try {
            $session = $this->sessionRepository->find(Uuid::fromString($id));

            if ($session === null) {
                return $this->json(['error' => 'Session non trouvée'], Response::HTTP_NOT_FOUND);
            }

            $participant = null;
            foreach ($session->getParticipants() as $p) {
                if ($p->getId()->toString() === $participantId) {
                    $participant = $p;
                    break;
                }
            }

            if ($participant === null) {
                return $this->json(['error' => 'Participant non trouvé'], Response::HTTP_NOT_FOUND);
            }

            $this->sessionService->updateParticipantPresence($participant, $currentDocumentId);

            return $this->json(['status' => 'ok']);
        } catch (\InvalidArgumentException $e) {
            return $this->json(['error' => $e->getMessage()], Response::HTTP_BAD_REQUEST);
        }
    }
}

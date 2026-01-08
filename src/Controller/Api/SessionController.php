<?php

namespace App\Controller\Api;

use App\Entity\Session;
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
        private readonly SessionService $sessionService,
        private readonly SessionRepository $sessionRepository,
    ) {}

    #[Route('', name: 'create', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        $data = $request->toArray();

        if (empty($data['title'])) {
            return $this->json(['error' => 'Le titre est requis'], Response::HTTP_BAD_REQUEST);
        }

        $session = $this->sessionService->create(
            $data['title'],
            $data['description'] ?? null
        );

        return $this->json($this->serializeSession($session), Response::HTTP_CREATED);
    }

    /**
     * Create a new session and automatically join as an AI agent.
     * This is the recommended endpoint for Claude or other AI agents.
     */
    #[Route('/agent/create', name: 'agent_create', methods: ['POST'])]
    public function agentCreate(Request $request): JsonResponse
    {
        $data = $request->toArray();

        if (empty($data['title'])) {
            return $this->json(['error' => 'Le titre est requis'], Response::HTTP_BAD_REQUEST);
        }

        $agentName = $data['agent_name'] ?? 'Claude Assistant';

        $session = $this->sessionService->create(
            $data['title'],
            $data['description'] ?? null
        );

        $participant = $this->sessionService->joinSession($session, $agentName, true);

        return $this->json([
            'session' => $this->serializeSession($session),
            'agent' => [
                'participant_id' => $participant->getId()->toString(),
                'pseudo' => $participant->getPseudo(),
                'color' => $participant->getColor(),
            ],
            'endpoints' => [
                'documents' => '/api/mcp/sessions/' . $session->getId()->toString() . '/documents',
                'status' => '/api/mcp/sessions/' . $session->getId()->toString() . '/status',
                'heartbeat' => '/api/sessions/' . $session->getId()->toString() . '/heartbeat',
            ],
            'invite_url' => '/session/join?code=' . $session->getInviteCode(),
        ], Response::HTTP_CREATED);
    }

    #[Route('', name: 'list', methods: ['GET'])]
    public function list(): JsonResponse
    {
        $sessions = $this->sessionRepository->findBy([], ['createdAt' => 'DESC'], 50);

        return $this->json([
            'sessions' => array_map(fn($s) => $this->serializeSession($s, true), $sessions),
        ]);
    }

    #[Route('/{id}', name: 'show', methods: ['GET'])]
    public function show(string $id): JsonResponse
    {
        try {
            $session = $this->sessionRepository->find(Uuid::fromString($id));

            if ($session === null) {
                return $this->json(['error' => 'Session non trouvée'], Response::HTTP_NOT_FOUND);
            }

            return $this->json($this->serializeSession($session, true));
        } catch (\InvalidArgumentException $e) {
            return $this->json(['error' => 'ID de session invalide'], Response::HTTP_BAD_REQUEST);
        }
    }

    #[Route('/join/{inviteCode}', name: 'join', methods: ['POST'])]
    public function join(string $inviteCode, Request $request): JsonResponse
    {
        $data = $request->toArray();

        if (empty($data['pseudo'])) {
            return $this->json(['error' => 'Le pseudo est requis'], Response::HTTP_BAD_REQUEST);
        }

        $session = $this->sessionService->findByInviteCode($inviteCode);

        if ($session === null) {
            return $this->json(['error' => 'Code d\'invitation invalide'], Response::HTTP_NOT_FOUND);
        }

        if ($session->getStatus() === Session::STATUS_ARCHIVE) {
            return $this->json(['error' => 'Cette session est archivée'], Response::HTTP_GONE);
        }

        $isAgent = $data['is_agent'] ?? false;
        $participant = $this->sessionService->joinSession($session, $data['pseudo'], $isAgent);

        return $this->json([
            'session' => $this->serializeSession($session),
            'participant' => [
                'id' => $participant->getId()->toString(),
                'pseudo' => $participant->getPseudo(),
                'color' => $participant->getColor(),
                'is_agent' => $participant->isAgent(),
            ],
        ]);
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
            $session = $this->sessionRepository->find(Uuid::fromString($id));

            if ($session === null) {
                return $this->json(['error' => 'Session non trouvée'], Response::HTTP_NOT_FOUND);
            }

            $data = $request->toArray();

            if (empty($data['status']) || !in_array($data['status'], [
                Session::STATUS_PREPARATION,
                Session::STATUS_EN_COURS,
                Session::STATUS_TERMINE,
                Session::STATUS_ARCHIVE,
            ])) {
                return $this->json(['error' => 'Statut invalide'], Response::HTTP_BAD_REQUEST);
            }

            $session = $this->sessionService->updateStatus($session, $data['status']);

            return $this->json($this->serializeSession($session));
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

    private function serializeSession(Session $session, bool $includeStats = false): array
    {
        $data = [
            'id' => $session->getId()->toString(),
            'title' => $session->getTitle(),
            'description' => $session->getDescription(),
            'status' => $session->getStatus(),
            'invite_code' => $session->getInviteCode(),
            'created_at' => $session->getCreatedAt()->format(\DateTimeInterface::ATOM),
            'updated_at' => $session->getUpdatedAt()->format(\DateTimeInterface::ATOM),
        ];

        if ($includeStats) {
            $data['document_count'] = $session->getDocuments()->count();
            $data['participant_count'] = $session->getParticipants()->count();
            $data['decision_count'] = $session->getDecisions()->count();
        }

        return $data;
    }
}

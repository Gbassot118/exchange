<?php

namespace App\AI\Tool;

use App\Repository\SessionRepository;
use Symfony\AI\Attribute\AsTool;
use Symfony\Component\Uid\Uuid;

#[AsTool(
    name: 'session_invite',
    description: 'Get the invite code and shareable invite link for a session. Use this to share the session with other participants who want to join.',
    parameters: [
        'session_id' => [
            'type' => 'string',
            'description' => 'The session UUID',
            'required' => true,
        ],
        'base_url' => [
            'type' => 'string',
            'description' => 'The base URL of the application (e.g., https://example.com). If not provided, a relative URL will be returned.',
            'required' => false,
        ],
    ]
)]
class SessionInviteTool
{
    public function __construct(
        private readonly SessionRepository $sessionRepository,
    ) {}

    public function __invoke(string $session_id, ?string $base_url = null): array
    {
        try {
            $session = $this->sessionRepository->find(Uuid::fromString($session_id));
            if ($session === null) {
                return ['error' => 'Session not found'];
            }

            $inviteCode = $session->getInviteCode();
            $relativePath = '/?code=' . $inviteCode;

            $inviteUrl = $base_url
                ? rtrim($base_url, '/') . $relativePath
                : $relativePath;

            return [
                'session' => [
                    'id' => $session->getId()->toString(),
                    'title' => $session->getTitle(),
                    'status' => $session->getStatus(),
                ],
                'invite_code' => $inviteCode,
                'invite_url' => $inviteUrl,
                'message' => sprintf(
                    'Share this link to invite participants to the session "%s": %s',
                    $session->getTitle(),
                    $inviteUrl
                ),
            ];
        } catch (\InvalidArgumentException $e) {
            return ['error' => 'Invalid session_id format'];
        }
    }
}

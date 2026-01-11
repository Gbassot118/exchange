<?php

declare(strict_types=1);

namespace App\AI\Tool;

use App\Application\DTO\Response\SessionResponse;
use App\Application\Query\Session\GetSessionQuery;
use Symfony\AI\Attribute\AsTool;
use Symfony\Component\Messenger\Exception\ExceptionInterface;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\HandledStamp;

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
        private readonly MessageBusInterface $messageBus,
    ) {}

    public function __invoke(string $session_id, ?string $base_url = null): array
    {
        try {
            $query = new GetSessionQuery(
                sessionId: $session_id,
                includeParticipants: false,
            );

            $envelope = $this->messageBus->dispatch($query);
            $handledStamp = $envelope->last(HandledStamp::class);

            if ($handledStamp === null) {
                return ['error' => 'Query was not handled'];
            }

            /** @var SessionResponse $session */
            $session = $handledStamp->getResult();

            $inviteCode = $session->inviteCode;
            $relativePath = '/?code=' . $inviteCode;

            $inviteUrl = $base_url
                ? rtrim($base_url, '/') . $relativePath
                : $relativePath;

            return [
                'session' => [
                    'id' => $session->id,
                    'title' => $session->title,
                    'status' => $session->status,
                ],
                'invite_code' => $inviteCode,
                'invite_url' => $inviteUrl,
                'message' => sprintf(
                    'Share this link to invite participants to the session "%s": %s',
                    $session->title,
                    $inviteUrl
                ),
            ];
        } catch (ExceptionInterface $e) {
            return ['error' => $e->getMessage()];
        } catch (\Exception $e) {
            return ['error' => $e->getMessage()];
        }
    }
}

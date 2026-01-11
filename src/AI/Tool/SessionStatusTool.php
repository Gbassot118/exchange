<?php

declare(strict_types=1);

namespace App\AI\Tool;

use App\Application\Command\Session\UpdateSessionStatusCommand;
use App\Application\DTO\Response\SessionResponse;
use App\Application\DTO\Response\SessionStatusResponse;
use App\Application\Query\Session\GetSessionStatusQuery;
use App\Domain\Session\ValueObject\SessionStatus;
use Symfony\AI\Attribute\AsTool;
use Symfony\Component\Messenger\Exception\ExceptionInterface;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\HandledStamp;

#[AsTool(
    name: 'session_status',
    description: 'Get or update the status of a collaborative documentation session. Supports viewing statistics and changing session status (preparation, en_cours, termine, archive).',
    parameters: [
        'operation' => [
            'type' => 'string',
            'description' => 'The operation to perform: get (view status and stats) or update (change status)',
            'enum' => ['get', 'update'],
            'required' => true,
        ],
        'session_id' => [
            'type' => 'string',
            'description' => 'The session UUID',
            'required' => true,
        ],
        'status' => [
            'type' => 'string',
            'description' => 'New status for the session (required for update operation)',
            'enum' => ['preparation', 'en_cours', 'termine', 'archive'],
        ],
    ]
)]
class SessionStatusTool
{
    public function __construct(
        private readonly MessageBusInterface $messageBus,
    ) {}

    public function __invoke(
        string $operation,
        string $session_id,
        ?string $status = null,
    ): array {
        return match ($operation) {
            'get' => $this->getStatus($session_id),
            'update' => $this->updateStatus($session_id, $status),
            default => ['error' => 'Unknown operation: ' . $operation],
        };
    }

    private function getStatus(string $sessionId): array
    {
        try {
            $query = new GetSessionStatusQuery(sessionId: $sessionId);

            $envelope = $this->messageBus->dispatch($query);
            $handledStamp = $envelope->last(HandledStamp::class);

            if ($handledStamp === null) {
                return ['error' => 'Query was not handled'];
            }

            /** @var SessionStatusResponse $statusResponse */
            $statusResponse = $handledStamp->getResult();

            return $this->formatResponse($statusResponse);
        } catch (ExceptionInterface $e) {
            return ['error' => $e->getMessage()];
        } catch (\Exception $e) {
            return ['error' => $e->getMessage()];
        }
    }

    private function updateStatus(string $sessionId, ?string $status): array
    {
        if (empty($status)) {
            return [
                'error' => 'status is required for update operation',
                'valid_statuses' => SessionStatus::allStatuses(),
            ];
        }

        try {
            $newStatus = SessionStatus::fromString($status);

            $command = new UpdateSessionStatusCommand(
                sessionId: $sessionId,
                newStatus: $newStatus,
            );

            $envelope = $this->messageBus->dispatch($command);
            $handledStamp = $envelope->last(HandledStamp::class);

            if ($handledStamp === null) {
                return ['error' => 'Command was not handled'];
            }

            /** @var SessionResponse $session */
            $session = $handledStamp->getResult();

            return [
                'success' => true,
                'session' => [
                    'id' => $session->id,
                    'title' => $session->title,
                    'status' => $session->status,
                    'updated_at' => $session->updatedAt,
                ],
                'message' => sprintf('Session status updated to "%s"', $status),
            ];
        } catch (\InvalidArgumentException $e) {
            return [
                'error' => $e->getMessage(),
                'valid_statuses' => SessionStatus::allStatuses(),
            ];
        } catch (ExceptionInterface $e) {
            return ['error' => $e->getMessage()];
        } catch (\Exception $e) {
            return ['error' => $e->getMessage()];
        }
    }

    private function formatResponse(SessionStatusResponse $response): array
    {
        $sessionData = $response->session->toArray();
        $stats = $response->statistics;

        // Build prioritized items from priority annotations
        $prioritizedItems = [];
        foreach ($response->priorityAnnotations as $annotation) {
            $annotationData = $annotation->toArray();
            $prioritizedItems[] = [
                'type' => $annotationData['type'],
                'id' => $annotationData['id'],
                'content' => $annotationData['content'],
                'author' => $annotationData['author'],
                'document_id' => $annotationData['document_id'],
            ];
        }

        return [
            'session' => [
                'id' => $sessionData['id'],
                'title' => $sessionData['title'],
                'description' => $sessionData['description'] ?? null,
                'status' => $sessionData['status'],
                'created_at' => $sessionData['created_at'],
            ],
            'statistics' => [
                'documents' => [
                    'total' => $stats->totalDocuments,
                ],
                'annotations' => [
                    'total' => $stats->openAnnotations + $stats->untreatedAnnotations,
                    'unresolved' => $stats->openAnnotations,
                    'untreated' => $stats->untreatedAnnotations,
                ],
                'decisions' => [
                    'pending_votes' => $stats->pendingDecisions,
                ],
                'participants' => [
                    'online' => $stats->onlineParticipants,
                ],
            ],
            'prioritized_items' => $prioritizedItems,
            'pending_decisions' => array_map(
                fn($d) => $d->toArray(),
                $response->decisions
            ),
            'valid_statuses' => SessionStatus::allStatuses(),
            'summary' => sprintf(
                'Session "%s" has %d documents, %d unresolved annotations, %d pending decisions. %d participants online.',
                $sessionData['title'],
                $stats->totalDocuments,
                $stats->openAnnotations,
                $stats->pendingDecisions,
                $stats->onlineParticipants
            ),
        ];
    }
}

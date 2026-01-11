<?php

declare(strict_types=1);

namespace App\AI\Tool;

use App\Application\DTO\Response\SessionStatusResponse;
use App\Application\Query\Session\GetSessionStatusQuery;
use Symfony\AI\Attribute\AsTool;
use Symfony\Component\Messenger\Exception\ExceptionInterface;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\HandledStamp;

#[AsTool(
    name: 'session_status',
    description: 'Get the current status of a collaborative documentation session, including statistics about documents, annotations, decisions, and online participants.',
    parameters: [
        'session_id' => [
            'type' => 'string',
            'description' => 'The session UUID',
            'required' => true,
        ],
    ]
)]
class SessionStatusTool
{
    public function __construct(
        private readonly MessageBusInterface $messageBus,
    ) {}

    public function __invoke(string $session_id): array
    {
        try {
            $query = new GetSessionStatusQuery(sessionId: $session_id);

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

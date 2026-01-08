<?php

namespace App\AI\Tool;

use App\Repository\AnnotationRepository;
use App\Repository\DecisionRepository;
use App\Repository\DocumentRepository;
use App\Repository\SessionRepository;
use App\Service\Session\SessionService;
use Symfony\AI\Attribute\AsTool;
use Symfony\Component\Uid\Uuid;

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
        private readonly SessionService $sessionService,
        private readonly SessionRepository $sessionRepository,
        private readonly DocumentRepository $documentRepository,
        private readonly AnnotationRepository $annotationRepository,
        private readonly DecisionRepository $decisionRepository,
    ) {}

    public function __invoke(string $session_id): array
    {
        try {
            $session = $this->sessionRepository->find(Uuid::fromString($session_id));
            if ($session === null) {
                return ['error' => 'Session not found'];
            }

            $documents = $this->documentRepository->findBySession($session, null, null);
            $onlineParticipants = $this->sessionService->getOnlineParticipants($session);
            $decisions = $this->decisionRepository->findBySession($session);

            // Count annotations by type and status
            $allAnnotations = $this->annotationRepository->findBySessionWithFilters($session, []);
            $annotationStats = [
                'total' => count($allAnnotations),
                'by_type' => [],
                'by_status' => [],
                'unresolved' => 0,
            ];

            foreach ($allAnnotations as $annotation) {
                $type = $annotation->getType();
                $status = $annotation->getStatus();

                $annotationStats['by_type'][$type] = ($annotationStats['by_type'][$type] ?? 0) + 1;
                $annotationStats['by_status'][$status] = ($annotationStats['by_status'][$status] ?? 0) + 1;

                if ($status === 'open') {
                    $annotationStats['unresolved']++;
                }
            }

            // Count decisions by status
            $decisionStats = [
                'total' => count($decisions),
                'by_status' => [],
                'pending_votes' => 0,
            ];

            foreach ($decisions as $decision) {
                $status = $decision->getStatus();
                $decisionStats['by_status'][$status] = ($decisionStats['by_status'][$status] ?? 0) + 1;

                if ($status === 'open') {
                    $decisionStats['pending_votes']++;
                }
            }

            // Prioritized items for the AI to focus on
            $prioritizedItems = [];

            // Add unresolved questions first
            $unresolvedQuestions = array_filter(
                $allAnnotations,
                fn($a) => $a->getType() === 'question' && $a->getStatus() === 'open'
            );
            foreach (array_slice($unresolvedQuestions, 0, 5) as $question) {
                $prioritizedItems[] = [
                    'type' => 'question',
                    'id' => $question->getId()->toString(),
                    'content' => $question->getContent(),
                    'author' => $question->getAuthor()->getPseudo(),
                    'document' => $question->getDocument()->getTitle(),
                ];
            }

            // Add unresolved objections
            $unresolvedObjections = array_filter(
                $allAnnotations,
                fn($a) => $a->getType() === 'objection' && $a->getStatus() === 'open'
            );
            foreach (array_slice($unresolvedObjections, 0, 5) as $objection) {
                $prioritizedItems[] = [
                    'type' => 'objection',
                    'id' => $objection->getId()->toString(),
                    'content' => $objection->getContent(),
                    'author' => $objection->getAuthor()->getPseudo(),
                    'document' => $objection->getDocument()->getTitle(),
                ];
            }

            return [
                'session' => [
                    'id' => $session->getId()->toString(),
                    'title' => $session->getTitle(),
                    'description' => $session->getDescription(),
                    'status' => $session->getStatus(),
                    'created_at' => $session->getCreatedAt()->format('c'),
                ],
                'statistics' => [
                    'documents' => [
                        'total' => count($documents),
                    ],
                    'annotations' => $annotationStats,
                    'decisions' => $decisionStats,
                    'participants' => [
                        'online' => count($onlineParticipants),
                        'list' => array_map(fn($p) => [
                            'id' => $p->getId()->toString(),
                            'pseudo' => $p->getPseudo(),
                            'is_agent' => $p->isAgent(),
                            'current_document' => $p->getCurrentDocumentId(),
                        ], $onlineParticipants),
                    ],
                ],
                'prioritized_items' => $prioritizedItems,
                'summary' => sprintf(
                    'Session "%s" has %d documents, %d annotations (%d unresolved), %d decisions (%d pending). %d participants online.',
                    $session->getTitle(),
                    count($documents),
                    $annotationStats['total'],
                    $annotationStats['unresolved'],
                    $decisionStats['total'],
                    $decisionStats['pending_votes'],
                    count($onlineParticipants)
                ),
            ];
        } catch (\InvalidArgumentException $e) {
            return ['error' => 'Invalid session_id format'];
        }
    }
}

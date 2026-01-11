<?php

declare(strict_types=1);

namespace App\Application\DTO\Response;

final readonly class SessionStatusResponse
{
    /**
     * @param array<DecisionResponse> $decisions
     * @param array<AnnotationResponse> $priorityAnnotations
     */
    public function __construct(
        public SessionResponse $session,
        public SessionStatistics $statistics,
        public array $decisions,
        public array $priorityAnnotations,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'session' => $this->session->toArray(),
            'statistics' => $this->statistics->toArray(),
            'decisions' => array_map(fn($d) => $d->toArray(), $this->decisions),
            'priority_annotations' => array_map(fn($a) => $a->toArray(), $this->priorityAnnotations),
        ];
    }
}

final readonly class SessionStatistics
{
    public function __construct(
        public int $totalDocuments,
        public int $openAnnotations,
        public int $untreatedAnnotations,
        public int $pendingDecisions,
        public int $onlineParticipants,
    ) {}

    /**
     * @return array<string, int>
     */
    public function toArray(): array
    {
        return [
            'total_documents' => $this->totalDocuments,
            'open_annotations' => $this->openAnnotations,
            'untreated_annotations' => $this->untreatedAnnotations,
            'pending_decisions' => $this->pendingDecisions,
            'online_participants' => $this->onlineParticipants,
        ];
    }
}

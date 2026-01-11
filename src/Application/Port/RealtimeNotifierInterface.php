<?php

declare(strict_types=1);

namespace App\Application\Port;

use App\Application\DTO\Response\AnnotationResponse;
use App\Application\DTO\Response\DecisionResponse;
use App\Application\DTO\Response\DocumentResponse;
use App\Application\DTO\Response\ParticipantResponse;

interface RealtimeNotifierInterface
{
    public function notifySessionStatusChanged(string $sessionId, string $newStatus): void;

    public function notifyParticipantJoined(string $sessionId, ParticipantResponse $participant): void;

    public function notifyDocumentCreated(string $sessionId, DocumentResponse $document): void;

    public function notifyDocumentUpdated(string $sessionId, DocumentResponse $document): void;

    public function notifyDocumentDeleted(string $sessionId, string $documentId): void;

    public function notifyAnnotationCreated(string $sessionId, AnnotationResponse $annotation): void;

    public function notifyAnnotationUpdated(string $sessionId, AnnotationResponse $annotation): void;

    public function notifyAnnotationResolved(string $sessionId, AnnotationResponse $annotation): void;

    public function notifyDecisionCreated(string $sessionId, DecisionResponse $decision): void;

    public function notifyDecisionUpdated(string $sessionId, DecisionResponse $decision): void;

    public function notifyDecisionValidated(string $sessionId, DecisionResponse $decision): void;

    public function notifyVoteCast(string $sessionId, DecisionResponse $decision): void;
}

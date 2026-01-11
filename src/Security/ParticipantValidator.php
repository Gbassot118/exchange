<?php

declare(strict_types=1);

namespace App\Security;

use App\Entity\Document;
use App\Entity\Participant;
use App\Entity\Session;
use App\Repository\AnnotationRepository;
use App\Repository\DocumentRepository;
use App\Repository\ParticipantRepository;
use App\Repository\SessionRepository;
use Symfony\Component\Uid\Uuid;

/**
 * Validates that a participant belongs to a session and has access to resources.
 *
 * This service provides security validation for the invite-code based authentication model.
 */
class ParticipantValidator
{
    public function __construct(
        private readonly SessionRepository $sessionRepository,
        private readonly ParticipantRepository $participantRepository,
        private readonly DocumentRepository $documentRepository,
        private readonly AnnotationRepository $annotationRepository,
    ) {}

    /**
     * Validates that a participant ID belongs to a given session.
     *
     * @throws InvalidParticipantException if validation fails
     */
    public function validateParticipantInSession(string $participantId, string $sessionId): Participant
    {
        if (!$this->isValidUuid($participantId)) {
            throw InvalidParticipantException::invalidFormat($participantId);
        }

        if (!$this->isValidUuid($sessionId)) {
            throw InvalidParticipantException::invalidSessionFormat($sessionId);
        }

        $session = $this->sessionRepository->find(Uuid::fromString($sessionId));
        if ($session === null) {
            throw InvalidParticipantException::sessionNotFound($sessionId);
        }

        $participant = $this->participantRepository->find(Uuid::fromString($participantId));
        if ($participant === null) {
            throw InvalidParticipantException::participantNotFound($participantId);
        }

        if ($participant->getSession()->getId()->toString() !== $sessionId) {
            throw InvalidParticipantException::notInSession($participantId, $sessionId);
        }

        return $participant;
    }

    /**
     * Validates that a participant ID has access to a specific document.
     * The participant must belong to the same session as the document.
     *
     * @throws InvalidParticipantException if validation fails
     */
    public function validateParticipantAccessToDocument(string $participantId, string $documentId): Participant
    {
        if (!$this->isValidUuid($participantId)) {
            throw InvalidParticipantException::invalidFormat($participantId);
        }

        if (!$this->isValidUuid($documentId)) {
            throw InvalidParticipantException::invalidDocumentFormat($documentId);
        }

        $document = $this->documentRepository->find(Uuid::fromString($documentId));
        if ($document === null) {
            throw InvalidParticipantException::documentNotFound($documentId);
        }

        $session = $document->getSession();
        $participant = $this->participantRepository->find(Uuid::fromString($participantId));

        if ($participant === null) {
            throw InvalidParticipantException::participantNotFound($participantId);
        }

        if ($participant->getSession()->getId()->toString() !== $session->getId()->toString()) {
            throw InvalidParticipantException::noAccessToDocument($participantId, $documentId);
        }

        return $participant;
    }

    /**
     * Validates that an agent ID (from X-Agent-Id header) belongs to the session.
     * Returns null if agentId is empty (allowing anonymous read access).
     *
     * @throws InvalidParticipantException if agentId is provided but invalid
     */
    public function validateAgentInSession(?string $agentId, string $sessionId): ?Participant
    {
        if (empty($agentId)) {
            return null;
        }

        return $this->validateParticipantInSession($agentId, $sessionId);
    }

    /**
     * Validates that an agent ID has write access to a document.
     * Agent must belong to the same session as the document.
     *
     * @throws InvalidParticipantException if agentId is empty or invalid
     */
    public function validateAgentWriteAccess(string $agentId, string $documentId): Participant
    {
        if (empty($agentId)) {
            throw InvalidParticipantException::agentRequired();
        }

        return $this->validateParticipantAccessToDocument($agentId, $documentId);
    }

    /**
     * Validates that a participant ID has access to a specific annotation.
     * The participant must belong to the same session as the annotation's document.
     *
     * @throws InvalidParticipantException if validation fails
     */
    public function validateParticipantAccessToAnnotation(string $participantId, string $annotationId): Participant
    {
        if (!$this->isValidUuid($participantId)) {
            throw InvalidParticipantException::invalidFormat($participantId);
        }

        if (!$this->isValidUuid($annotationId)) {
            throw InvalidParticipantException::invalidAnnotationFormat($annotationId);
        }

        $annotation = $this->annotationRepository->find(Uuid::fromString($annotationId));
        if ($annotation === null) {
            throw InvalidParticipantException::annotationNotFound($annotationId);
        }

        $session = $annotation->getDocument()->getSession();
        $participant = $this->participantRepository->find(Uuid::fromString($participantId));

        if ($participant === null) {
            throw InvalidParticipantException::participantNotFound($participantId);
        }

        if ($participant->getSession()->getId()->toString() !== $session->getId()->toString()) {
            throw InvalidParticipantException::noAccessToAnnotation($participantId, $annotationId);
        }

        return $participant;
    }

    /**
     * Validates UUID format.
     */
    private function isValidUuid(string $value): bool
    {
        return Uuid::isValid($value);
    }
}

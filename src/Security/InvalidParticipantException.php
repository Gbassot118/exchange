<?php

declare(strict_types=1);

namespace App\Security;

use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * Exception thrown when participant validation fails.
 */
class InvalidParticipantException extends HttpException
{
    public static function invalidFormat(string $participantId): self
    {
        return new self(400, sprintf('Format de participant_id invalide: %s', $participantId));
    }

    public static function invalidSessionFormat(string $sessionId): self
    {
        return new self(400, sprintf('Format de session_id invalide: %s', $sessionId));
    }

    public static function invalidDocumentFormat(string $documentId): self
    {
        return new self(400, sprintf('Format de document_id invalide: %s', $documentId));
    }

    public static function participantNotFound(string $participantId): self
    {
        return new self(404, sprintf('Participant non trouvé: %s', $participantId));
    }

    public static function sessionNotFound(string $sessionId): self
    {
        return new self(404, sprintf('Session non trouvée: %s', $sessionId));
    }

    public static function documentNotFound(string $documentId): self
    {
        return new self(404, sprintf('Document non trouvé: %s', $documentId));
    }

    public static function notInSession(string $participantId, string $sessionId): self
    {
        return new self(403, sprintf(
            'Le participant %s n\'appartient pas à la session %s',
            $participantId,
            $sessionId
        ));
    }

    public static function noAccessToDocument(string $participantId, string $documentId): self
    {
        return new self(403, sprintf(
            'Le participant %s n\'a pas accès au document %s',
            $participantId,
            $documentId
        ));
    }

    public static function agentRequired(): self
    {
        return new self(401, 'X-Agent-Id header est requis pour cette opération');
    }

    public static function invalidAnnotationFormat(string $annotationId): self
    {
        return new self(400, sprintf('Format d\'annotation_id invalide: %s', $annotationId));
    }

    public static function annotationNotFound(string $annotationId): self
    {
        return new self(404, sprintf('Annotation non trouvée: %s', $annotationId));
    }

    public static function noAccessToAnnotation(string $participantId, string $annotationId): self
    {
        return new self(403, sprintf(
            'Le participant %s n\'a pas accès à l\'annotation %s',
            $participantId,
            $annotationId
        ));
    }
}

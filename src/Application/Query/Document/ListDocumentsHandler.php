<?php

declare(strict_types=1);

namespace App\Application\Query\Document;

use App\Application\DTO\Response\DocumentResponse;
use App\Application\DTO\Transformer\DocumentTransformer;
use App\Domain\Document\Repository\DocumentRepositoryInterface;
use App\Domain\Session\Exception\SessionNotFoundException;
use App\Domain\Session\Repository\SessionRepositoryInterface;
use App\Domain\Session\ValueObject\SessionId;

final readonly class ListDocumentsHandler
{
    public function __construct(
        private SessionRepositoryInterface $sessionRepository,
        private DocumentRepositoryInterface $documentRepository,
        private DocumentTransformer $documentTransformer,
    ) {}

    /**
     * @return array<DocumentResponse>
     */
    public function __invoke(ListDocumentsQuery $query): array
    {
        $sessionId = SessionId::fromString($query->sessionId);
        $session = $this->sessionRepository->findById($sessionId);

        if ($session === null) {
            throw SessionNotFoundException::withId($query->sessionId);
        }

        $documents = $this->documentRepository->findBySession(
            $session,
            $query->parentId,
            $query->type,
        );

        return $this->documentTransformer->toResponseList($documents, $query->includeContent);
    }
}

<?php

declare(strict_types=1);

namespace App\Application\Query\Estimation;

use App\Application\DTO\Response\EstimationResponse;
use App\Application\DTO\Transformer\EstimationTransformer;
use App\Domain\Document\Repository\DocumentRepositoryInterface;
use App\Domain\Document\ValueObject\DocumentId;
use App\Domain\Estimation\Repository\EstimationRepositoryInterface;
use App\Domain\Session\Exception\SessionNotFoundException;
use App\Domain\Session\Repository\SessionRepositoryInterface;
use App\Domain\Session\ValueObject\SessionId;

final readonly class ListEstimationsHandler
{
    public function __construct(
        private SessionRepositoryInterface $sessionRepository,
        private DocumentRepositoryInterface $documentRepository,
        private EstimationRepositoryInterface $estimationRepository,
        private EstimationTransformer $estimationTransformer,
    ) {}

    /**
     * @return array<EstimationResponse>
     */
    public function __invoke(ListEstimationsQuery $query): array
    {
        $sessionId = SessionId::fromString($query->sessionId);
        $session = $this->sessionRepository->findById($sessionId);

        if ($session === null) {
            throw SessionNotFoundException::withId($query->sessionId);
        }

        if ($query->documentId !== null) {
            $documentId = DocumentId::fromString($query->documentId);
            $document = $this->documentRepository->findById($documentId);
            if ($document !== null) {
                $estimations = $this->estimationRepository->findByDocument($document);
                return $this->estimationTransformer->toResponseList($estimations);
            }
            return [];
        }

        if ($query->openOnly) {
            $estimations = $this->estimationRepository->findOpenBySession($session);
            return $this->estimationTransformer->toResponseList($estimations, false);
        }

        if ($query->status !== null) {
            $estimations = $this->estimationRepository->findByStatus($session, $query->status);
            return $this->estimationTransformer->toResponseList($estimations);
        }

        $estimations = $this->estimationRepository->findBySession($session);
        return $this->estimationTransformer->toResponseList($estimations);
    }
}

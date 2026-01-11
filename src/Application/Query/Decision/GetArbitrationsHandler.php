<?php

declare(strict_types=1);

namespace App\Application\Query\Decision;

use App\Domain\Decision\Repository\DecisionRepositoryInterface;
use App\Domain\Session\Exception\SessionNotFoundException;
use App\Domain\Session\Repository\SessionRepositoryInterface;
use App\Domain\Session\ValueObject\SessionId;
use App\Entity\Decision;

final readonly class GetArbitrationsHandler
{
    public function __construct(
        private SessionRepositoryInterface $sessionRepository,
        private DecisionRepositoryInterface $decisionRepository,
    ) {}

    /**
     * @return array<array{id: string, title: string, description: ?string, selected_option: ?array, document: ?array, validated_at: string, vote_count: int}>
     */
    public function __invoke(GetArbitrationsQuery $query): array
    {
        $sessionId = SessionId::fromString($query->sessionId);
        $session = $this->sessionRepository->findById($sessionId);

        if ($session === null) {
            throw SessionNotFoundException::withId($query->sessionId);
        }

        $decisions = $this->decisionRepository->findByStatus($session, Decision::STATUS_VALIDE);

        $arbitrations = [];
        foreach ($decisions as $decision) {
            $selectedOption = null;
            $selectedOptionId = $decision->getSelectedOptionId()?->toString();
            foreach ($decision->getOptions() as $option) {
                if ($option['id'] === $selectedOptionId) {
                    $selectedOption = $option;
                    break;
                }
            }

            $arbitrations[] = [
                'id' => $decision->getId()->toString(),
                'title' => $decision->getTitle(),
                'description' => $decision->getDescription(),
                'selected_option' => $selectedOption,
                'document' => $decision->getLinkedDocument() ? [
                    'id' => $decision->getLinkedDocument()->getId()->toString(),
                    'title' => $decision->getLinkedDocument()->getTitle(),
                    'slug' => $decision->getLinkedDocument()->getSlug(),
                ] : null,
                'validated_at' => $decision->getUpdatedAt()->format(\DateTimeInterface::ATOM),
                'vote_count' => $decision->getVotes()->count(),
            ];
        }

        return $arbitrations;
    }
}

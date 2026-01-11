<?php

declare(strict_types=1);

namespace App\Application\Query\Session;

use App\Application\DTO\Response\SessionResponse;
use App\Application\DTO\Transformer\SessionTransformer;
use App\Domain\Session\Repository\SessionRepositoryInterface;

final readonly class ListSessionsHandler
{
    public function __construct(
        private SessionRepositoryInterface $sessionRepository,
        private SessionTransformer $sessionTransformer,
    ) {}

    /**
     * @return array<SessionResponse>
     */
    public function __invoke(ListSessionsQuery $query): array
    {
        $sessions = $query->activeOnly
            ? $this->sessionRepository->findActive()
            : $this->sessionRepository->findAllSessions($query->limit);

        return $this->sessionTransformer->toResponseList($sessions);
    }
}

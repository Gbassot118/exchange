<?php

declare(strict_types=1);

namespace App\AI\Tool;

use App\Application\Command\Estimation\CreateEstimationCommand;
use App\Application\Command\Estimation\RevealEstimationCommand;
use App\Application\Command\Estimation\VoteEstimationCommand;
use App\Application\DTO\Response\EstimationResponse;
use App\Application\Query\Estimation\GetEstimationQuery;
use App\Application\Query\Estimation\ListEstimationsQuery;
use App\Domain\Estimation\ValueObject\FibonacciValue;
use Symfony\AI\Attribute\AsTool;
use Symfony\Component\Messenger\Exception\ExceptionInterface;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\HandledStamp;

#[AsTool(
    name: 'estimation_operations',
    description: 'Tool for managing Planning Poker estimations in a collaborative session. Supports listing, creating, voting on and revealing estimations.',
    parameters: [
        'operation' => [
            'type' => 'string',
            'description' => 'The operation to perform: list, read, create, vote, reveal',
            'enum' => ['list', 'read', 'create', 'vote', 'reveal'],
            'required' => true,
        ],
        'session_id' => [
            'type' => 'string',
            'description' => 'The session UUID (required for list and create operations)',
        ],
        'estimation_id' => [
            'type' => 'string',
            'description' => 'The estimation UUID (required for read, vote, and reveal operations)',
        ],
        'participant_id' => [
            'type' => 'string',
            'description' => 'The participant UUID of the AI agent (required for vote)',
        ],
        'title' => [
            'type' => 'string',
            'description' => 'Estimation title (for create)',
        ],
        'description' => [
            'type' => 'string',
            'description' => 'Estimation description (for create, optional)',
        ],
        'document_id' => [
            'type' => 'string',
            'description' => 'Link estimation to a document UUID (for create, optional)',
        ],
        'value' => [
            'type' => 'string',
            'description' => 'Fibonacci vote value: 0, 1, 2, 3, 5, 8, 13, 21, or ? (for vote)',
            'enum' => ['0', '1', '2', '3', '5', '8', '13', '21', '?'],
        ],
        'open_only' => [
            'type' => 'boolean',
            'description' => 'Filter to show only open estimations (for list, default: false)',
        ],
    ]
)]
class EstimationTool
{
    public function __construct(
        private readonly MessageBusInterface $messageBus,
    ) {}

    public function __invoke(
        string $operation,
        ?string $session_id = null,
        ?string $estimation_id = null,
        ?string $participant_id = null,
        ?string $title = null,
        ?string $description = null,
        ?string $document_id = null,
        ?string $value = null,
        ?bool $open_only = false,
    ): array {
        return match ($operation) {
            'list' => $this->listEstimations($session_id, $open_only),
            'read' => $this->readEstimation($estimation_id),
            'create' => $this->createEstimation($session_id, $title, $description, $document_id),
            'vote' => $this->voteEstimation($estimation_id, $participant_id, $value),
            'reveal' => $this->revealEstimation($estimation_id),
            default => ['error' => 'Unknown operation: ' . $operation],
        };
    }

    private function listEstimations(?string $sessionId, ?bool $openOnly): array
    {
        if (empty($sessionId)) {
            return ['error' => 'session_id is required for list operation'];
        }

        try {
            $query = new ListEstimationsQuery(
                sessionId: $sessionId,
                openOnly: $openOnly ?? false,
            );

            $envelope = $this->messageBus->dispatch($query);
            $handledStamp = $envelope->last(HandledStamp::class);

            if ($handledStamp === null) {
                return ['error' => 'Query was not handled'];
            }

            /** @var array<EstimationResponse> $estimations */
            $estimations = $handledStamp->getResult();

            return [
                'estimations' => array_map(
                    fn(EstimationResponse $est) => $est->toArray(),
                    $estimations
                ),
                'fibonacci_values' => FibonacciValue::VALUES,
            ];
        } catch (ExceptionInterface $e) {
            return ['error' => $e->getMessage()];
        } catch (\Exception $e) {
            return ['error' => $e->getMessage()];
        }
    }

    private function readEstimation(?string $estimationId): array
    {
        if (empty($estimationId)) {
            return ['error' => 'estimation_id is required for read operation'];
        }

        try {
            $query = new GetEstimationQuery(
                estimationId: $estimationId,
            );

            $envelope = $this->messageBus->dispatch($query);
            $handledStamp = $envelope->last(HandledStamp::class);

            if ($handledStamp === null) {
                return ['error' => 'Query was not handled'];
            }

            /** @var EstimationResponse $estimation */
            $estimation = $handledStamp->getResult();

            return [
                'estimation' => $estimation->toArray(),
                'fibonacci_values' => FibonacciValue::VALUES,
            ];
        } catch (ExceptionInterface $e) {
            return ['error' => $e->getMessage()];
        } catch (\Exception $e) {
            return ['error' => $e->getMessage()];
        }
    }

    private function createEstimation(
        ?string $sessionId,
        ?string $title,
        ?string $description,
        ?string $documentId,
    ): array {
        if (empty($sessionId)) {
            return ['error' => 'session_id is required for create operation'];
        }
        if (empty($title)) {
            return ['error' => 'title is required for create operation'];
        }

        try {
            $command = new CreateEstimationCommand(
                sessionId: $sessionId,
                title: $title,
                description: $description,
                linkedDocumentId: $documentId,
            );

            $envelope = $this->messageBus->dispatch($command);
            $handledStamp = $envelope->last(HandledStamp::class);

            if ($handledStamp === null) {
                return ['error' => 'Command was not handled'];
            }

            /** @var EstimationResponse $estimation */
            $estimation = $handledStamp->getResult();

            return [
                'success' => true,
                'estimation' => [
                    'id' => $estimation->id,
                    'title' => $estimation->title,
                    'status' => $estimation->status,
                ],
                'message' => 'Estimation created. Participants can now vote using Fibonacci values.',
                'fibonacci_values' => FibonacciValue::VALUES,
            ];
        } catch (ExceptionInterface $e) {
            return ['error' => $e->getMessage()];
        } catch (\Exception $e) {
            return ['error' => $e->getMessage()];
        }
    }

    private function voteEstimation(
        ?string $estimationId,
        ?string $participantId,
        ?string $value,
    ): array {
        if (empty($estimationId)) {
            return ['error' => 'estimation_id is required for vote operation'];
        }
        if (empty($participantId)) {
            return ['error' => 'participant_id is required for vote operation'];
        }
        if ($value === null) {
            return [
                'error' => 'value is required for vote operation',
                'valid_values' => FibonacciValue::VALUES,
            ];
        }
        if (!FibonacciValue::isValid($value)) {
            return [
                'error' => 'Invalid Fibonacci value',
                'valid_values' => FibonacciValue::VALUES,
            ];
        }

        try {
            $command = new VoteEstimationCommand(
                estimationId: $estimationId,
                participantId: $participantId,
                value: $value,
            );

            $envelope = $this->messageBus->dispatch($command);
            $handledStamp = $envelope->last(HandledStamp::class);

            if ($handledStamp === null) {
                return ['error' => 'Command was not handled'];
            }

            /** @var EstimationResponse $estimation */
            $estimation = $handledStamp->getResult();

            return [
                'success' => true,
                'voted' => true,
                'estimation' => [
                    'id' => $estimation->id,
                    'title' => $estimation->title,
                    'vote_count' => $estimation->voteCount,
                    'status' => $estimation->status,
                ],
            ];
        } catch (ExceptionInterface $e) {
            return ['error' => $e->getMessage()];
        } catch (\Exception $e) {
            return ['error' => $e->getMessage()];
        }
    }

    private function revealEstimation(?string $estimationId): array
    {
        if (empty($estimationId)) {
            return ['error' => 'estimation_id is required for reveal operation'];
        }

        try {
            $command = new RevealEstimationCommand(
                estimationId: $estimationId,
            );

            $envelope = $this->messageBus->dispatch($command);
            $handledStamp = $envelope->last(HandledStamp::class);

            if ($handledStamp === null) {
                return ['error' => 'Command was not handled'];
            }

            /** @var EstimationResponse $estimation */
            $estimation = $handledStamp->getResult();

            return [
                'success' => true,
                'estimation' => $estimation->toArray(),
                'message' => 'Votes revealed. Average: ' . ($estimation->average ?? 'N/A'),
            ];
        } catch (ExceptionInterface $e) {
            return ['error' => $e->getMessage()];
        } catch (\Exception $e) {
            return ['error' => $e->getMessage()];
        }
    }
}

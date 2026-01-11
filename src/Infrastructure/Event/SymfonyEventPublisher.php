<?php

declare(strict_types=1);

namespace App\Infrastructure\Event;

use App\Application\Port\EventPublisherInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

final readonly class SymfonyEventPublisher implements EventPublisherInterface
{
    public function __construct(
        private EventDispatcherInterface $dispatcher,
    ) {}

    public function publish(object $event): void
    {
        $this->dispatcher->dispatch($event);
    }

    public function publishAll(array $events): void
    {
        foreach ($events as $event) {
            $this->publish($event);
        }
    }
}

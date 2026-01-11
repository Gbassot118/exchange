<?php

declare(strict_types=1);

namespace App\Application\Port;

interface EventPublisherInterface
{
    public function publish(object $event): void;

    /**
     * @param array<object> $events
     */
    public function publishAll(array $events): void;
}

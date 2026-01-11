<?php

declare(strict_types=1);

namespace App\Application\Command;

interface CommandBusInterface
{
    /**
     * Dispatch a command to its handler.
     *
     * @template T
     * @param object $command The command to dispatch
     * @return mixed The result from the handler (if any)
     */
    public function dispatch(object $command): mixed;
}

<?php

declare(strict_types=1);

namespace App\Application\Query;

interface QueryBusInterface
{
    /**
     * Dispatch a query to its handler.
     *
     * @template T
     * @param object $query The query to dispatch
     * @return mixed The result from the handler
     */
    public function dispatch(object $query): mixed;
}

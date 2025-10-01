<?php

declare(strict_types=1);

namespace Bob\Events;

/**
 * PSR-14 compatible Event Dispatcher Interface
 *
 * Simplified interface for dispatching migration events.
 * Compatible with PSR-14 event dispatcher implementations.
 */
interface EventDispatcherInterface
{
    /**
     * Dispatch an event
     *
     * @param  string  $eventName  The name of the event to dispatch
     * @param  array  $payload  Additional data to pass with the event
     */
    public function dispatch(string $eventName, array $payload = []): void;
}

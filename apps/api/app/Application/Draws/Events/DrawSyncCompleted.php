<?php

namespace App\Application\Draws\Events;

use App\Infrastructure\Persistence\Eloquent\Models\SyncRun;

/**
 * Consumers must be idempotent: queue delivery is at-least-once, not exactly-once.
 */
final readonly class DrawSyncCompleted
{
    public function __construct(public SyncRun $syncRun) {}
}

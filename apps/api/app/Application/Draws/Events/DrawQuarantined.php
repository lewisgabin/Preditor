<?php

namespace App\Application\Draws\Events;

use App\Infrastructure\Persistence\Eloquent\Models\DrawQuarantine;
use App\Infrastructure\Persistence\Eloquent\Models\SyncRun;

final readonly class DrawQuarantined
{
    public function __construct(public DrawQuarantine $quarantine, public SyncRun $syncRun) {}
}

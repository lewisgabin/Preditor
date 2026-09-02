<?php

namespace App\Application\Draws\Events;

use App\Infrastructure\Persistence\Eloquent\Models\Draw;
use App\Infrastructure\Persistence\Eloquent\Models\DrawCorrection;
use App\Infrastructure\Persistence\Eloquent\Models\SyncRun;

final readonly class DrawCorrected
{
    public function __construct(public Draw $draw, public DrawCorrection $correction, public SyncRun $syncRun) {}
}

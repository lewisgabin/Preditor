<?php

namespace App\Console\Commands;

use App\Application\Draws\Services\DispatchCurrentDrawSyncs as Dispatcher;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('draws:dispatch-current')]
#[Description('Dispatches eligible current-day lottery draw synchronizations.')]
class DispatchCurrentDrawSyncs extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(Dispatcher $dispatcher): int
    {
        $summary = $dispatcher->handle();
        $this->info(json_encode($summary, JSON_THROW_ON_ERROR));

        return self::SUCCESS;
    }
}

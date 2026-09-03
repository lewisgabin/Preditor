<?php

namespace App\Console\Commands;

use App\Application\Draws\Services\SyncLotteryDraws as Synchronizer;
use App\Console\Commands\Concerns\ExecutesLotteryDrawCommand;
use App\Domain\Draws\Enums\SyncTrigger;
use Illuminate\Console\Command;

final class SyncLotteryDraws extends Command
{
    use ExecutesLotteryDrawCommand;

    protected $signature = 'draws:sync
        {--lottery= : External lottery ID}
        {--date= : Local date in YYYY-MM-DD}
        {--from= : First local date in YYYY-MM-DD}
        {--to= : Last local date in YYYY-MM-DD}
        {--provider= : Lottery draw provider}
        {--dry-run : Fetch and normalize without persisting draw effects}
        {--force : Allow a manual invocation while the provider integration is disabled}';

    protected $description = 'Queue a lottery draw synchronization.';

    public function handle(Synchronizer $sync): int
    {
        return $this->executeCommand($sync);
    }

    protected function trigger(): SyncTrigger
    {
        return SyncTrigger::Manual;
    }
}

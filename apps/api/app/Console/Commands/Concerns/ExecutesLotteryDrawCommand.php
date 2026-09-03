<?php

namespace App\Console\Commands\Concerns;

use App\Application\Draws\Data\DrawFetchRequest;
use App\Application\Draws\Services\SyncLotteryDraws;
use App\Domain\Draws\Enums\SyncTrigger;
use App\Infrastructure\Draws\Jobs\SyncLotteryDrawsJob;
use App\Infrastructure\Persistence\Eloquent\Models\Lottery;
use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Console\Command;
use InvalidArgumentException;
use Throwable;

trait ExecutesLotteryDrawCommand
{
    abstract protected function trigger(): SyncTrigger;

    protected function executeCommand(SyncLotteryDraws $sync): int
    {
        try {
            [$provider, $lotteries, $date, $rangeStart, $rangeEnd] = $this->buildInput();
            $force = (bool) $this->option('force');
            if ($force && $this->trigger() !== SyncTrigger::Manual) {
                throw new InvalidArgumentException('The --force option is only available for manual synchronization.');
            }

            foreach ($lotteries as $externalId) {
                $request = new DrawFetchRequest($provider, $externalId, $this->trigger(), $date, $rangeStart, $rangeEnd);
                $sync->validateRequest($request, $force);
            }
        } catch (Throwable $exception) {
            $this->error($this->safeMessage($exception));

            return Command::FAILURE;
        }

        foreach ($lotteries as $externalId) {
            $request = new DrawFetchRequest($provider, $externalId, $this->trigger(), $date, $rangeStart, $rangeEnd);
            $dryRun = (bool) $this->option('dry-run');
            $run = $sync->createRun($request, $dryRun ? ['dry_run' => true] : []);

            if ($dryRun) {
                $sync->runDry($run->uuid, $request);
                $this->line("Dry run completed for lottery {$externalId}: {$run->uuid}");

                continue;
            }

            SyncLotteryDrawsJob::dispatch($run->uuid, $request);
            $this->line("Lottery draw sync queued for lottery {$externalId}: {$run->uuid}");
        }

        return Command::SUCCESS;
    }

    /** @return array{string, list<int>, ?DateTimeImmutable, ?DateTimeImmutable, ?DateTimeImmutable} */
    private function buildInput(): array
    {
        $provider = trim((string) ($this->option('provider') ?: config('lottery-api.provider')));
        if ($provider === '') {
            throw new InvalidArgumentException('A lottery draw provider is required.');
        }

        $date = $this->dateOption('date');
        $from = $this->dateOption('from');
        $to = $this->dateOption('to');
        if ($date !== null && ($from !== null || $to !== null)) {
            throw new InvalidArgumentException('The --date option cannot be combined with --from or --to.');
        }
        if (($from === null) !== ($to === null)) {
            throw new InvalidArgumentException('Both --from and --to are required for a date range.');
        }

        $lottery = $this->option('lottery');
        if ($lottery !== null && $lottery !== '') {
            if (preg_match('/^[1-9][0-9]*$/D', $lottery) !== 1) {
                throw new InvalidArgumentException('The --lottery option must be a positive external lottery ID.');
            }
            if (! Lottery::query()->where('external_id', (int) $lottery)->exists()) {
                throw new InvalidArgumentException('The requested lottery does not exist.');
            }

            return [$provider, [(int) $lottery], $date, $from, $to];
        }

        if ($provider === 'elboletoganador') {
            throw new InvalidArgumentException('The real lottery draw provider requires --lottery.');
        }

        $lotteries = Lottery::query()->where('is_active', true)->orderBy('external_id')->pluck('external_id')->map(static fn (mixed $id): int => (int) $id)->all();
        if ($lotteries === []) {
            throw new InvalidArgumentException('No active lotteries are available to synchronize.');
        }

        return [$provider, $lotteries, $date, $from, $to];
    }

    private function dateOption(string $name): ?DateTimeImmutable
    {
        $value = $this->option($name);
        if ($value === null || $value === '') {
            return null;
        }
        if (! is_string($value)) {
            throw new InvalidArgumentException("The --{$name} option must use YYYY-MM-DD.");
        }

        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value, new DateTimeZone('America/Santo_Domingo'));
        $errors = DateTimeImmutable::getLastErrors();
        if ($date === false || ($errors !== false && ($errors['warning_count'] > 0 || $errors['error_count'] > 0)) || $date->format('Y-m-d') !== $value) {
            throw new InvalidArgumentException("The --{$name} option must use YYYY-MM-DD.");
        }

        return $date;
    }

    private function safeMessage(Throwable $exception): string
    {
        return $exception instanceof InvalidArgumentException || $exception instanceof \LogicException
            ? $exception->getMessage()
            : 'The lottery draw command could not be prepared.';
    }
}

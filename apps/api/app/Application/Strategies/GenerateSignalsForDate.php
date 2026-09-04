<?php

namespace App\Application\Strategies;

use App\Domain\Strategies\MethodCode;
use App\Infrastructure\Persistence\Eloquent\Models\Method;
use App\Infrastructure\Persistence\Eloquent\Models\Signal;
use Throwable;

final readonly class GenerateSignalsForDate
{
    public function __construct(private GenerateSignal $generate) {}

    /** @param list<string> $codes
     * @return array{generated: int, already_exists: int, missing_source: int, timing_blocked: int, error: int, signals: list<Signal>, outcomes: list<array{method_code: string, outcome: string, reason: ?string}>}
     */
    public function __invoke(string $date, array $codes = [], bool $dryRun = false): array
    {
        if ($codes === []) {
            $codes = Method::query()->where('is_active', true)->orderBy('code')->get()->map(fn (Method $m): string => $m->code->value)->all();
        }
        $summary = ['generated' => 0, 'already_exists' => 0, 'missing_source' => 0, 'timing_blocked' => 0, 'error' => 0, 'signals' => [], 'outcomes' => []];
        foreach (array_unique($codes) as $code) {
            $reason = null;
            try {
                $result = ($this->generate)(MethodCode::from($code), $date, $dryRun);
                $outcome = $result['outcome'];
                $summary['signals'][] = $result['signal'];
            } catch (GenerationBlocked $blocked) {
                $outcome = $blocked->outcome;
                $reason = $blocked->reason;
            } catch (Throwable $exception) {
                report($exception);
                $outcome = 'error';
                $reason = 'generation_failed';
            }
            $key = match ($outcome) {
                'skipped_missing_source' => 'missing_source', 'skipped_timing' => 'timing_blocked', default => $outcome
            };
            $summary[$key]++;
            $summary['outcomes'][] = ['method_code' => $code, 'outcome' => $outcome, 'reason' => $reason];
        }

        return $summary;
    }
}

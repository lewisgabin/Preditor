<?php

namespace App\Application\Strategies;

use App\Domain\Strategies\MethodCode;
use App\Domain\Strategies\OperatorDefinition;
use App\Domain\Strategies\OperatorRegistry;
use App\Infrastructure\Persistence\Eloquent\Models\Method;
use App\Infrastructure\Persistence\Eloquent\Models\MethodVersion;
use App\Infrastructure\Persistence\Eloquent\Models\Signal;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

final readonly class GenerateSignal
{
    public function __construct(private ResolveSignalSources $resolve, private OperatorRegistry $operators) {}

    /** @return array{outcome: string, signal: Signal} */
    public function __invoke(MethodCode $code, string $targetDate, bool $dryRun = false): array
    {
        $date = CarbonImmutable::createFromFormat('!Y-m-d', $targetDate, 'America/Santo_Domingo');
        if ($date === null || $date->toDateString() !== $targetDate) {
            throw new \InvalidArgumentException('Fecha inválida.');
        }

        return DB::transaction(function () use ($code, $date, $dryRun): array {
            $method = Method::query()->where('code', $code->value)->where('is_active', true)->lockForUpdate()->first();
            if ($method === null) {
                throw new GenerationBlocked('error', 'method_inactive_or_missing');
            }
            $version = MethodVersion::query()->where('method_id', $method->id)->where('is_active', true)
                ->whereDate('valid_from', '<=', $date->toDateString())
                ->where(fn ($q) => $q->whereNull('valid_to')->orWhereDate('valid_to', '>=', $date->toDateString()))
                ->orderByDesc('version')->lockForUpdate()->first();
            if ($version === null) {
                throw new GenerationBlocked('error', 'version_unavailable');
            }
            $identity = ['method_version_id' => $version->id, 'target_lottery_id' => $version->target_lottery_id, 'target_draw_date_local' => $date->toDateString()];
            $existing = Signal::query()->where($identity)->first();
            if ($existing !== null) {
                return ['outcome' => 'already_exists', 'signal' => $existing];
            }
            $resolved = ($this->resolve)($version, $date);
            $draw = $resolved['draw'];
            $definition = OperatorDefinition::fromArray($version->operator_definition);
            $prizes = ['P1' => $draw->p1, 'P2' => $draw->p2, 'P3' => $draw->p3];
            $number = $this->operators->calculate($definition, $prizes);
            $snapshot = [
                'method_code' => $code->value, 'method_name' => $method->name, 'version' => $version->version,
                'source_draw_id' => $draw->id,
                'source_values' => ['p1' => $draw->p1->value(), 'p2' => $draw->p2->value(), 'p3' => $draw->p3->value()],
                'sources' => [['draw_id' => $draw->id, 'lottery_name' => $draw->lottery->name, 'date' => $draw->draw_date_local?->toDateString(), 'p1' => $draw->p1->value(), 'p2' => $draw->p2->value(), 'p3' => $draw->p3->value()]],
                'operator' => $definition->type->value, 'arguments' => $version->operator_definition,
                'result' => $number->value(), 'explanation' => $this->operators->explain($definition, $prizes),
                'as_of_utc' => $resolved['cutoff']->toIso8601String(),
            ];
            $expired = $resolved['target_time']?->lte(CarbonImmutable::now('UTC')) ?? $date->lt(CarbonImmutable::today('America/Santo_Domingo'));
            $signal = new Signal($identity + ['recommended_number' => $number->value(), 'status' => $expired ? 'expired' : 'generated',
                'generated_at' => CarbonImmutable::now('UTC'), 'expires_at' => $resolved['target_time'], 'calculation_snapshot' => $snapshot]);
            if (! $dryRun) {
                $signal->save();
                $signal->sources()->create(['draw_id' => $draw->id, 'role' => 'input', 'source_order' => 1]);
            }

            return ['outcome' => 'generated', 'signal' => $signal];
        }, 3);
    }
}

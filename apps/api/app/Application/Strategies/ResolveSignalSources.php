<?php

namespace App\Application\Strategies;

use App\Domain\Strategies\SourceDayRelation;
use App\Domain\Strategies\SourceDefinition;
use App\Infrastructure\Persistence\Eloquent\Models\Draw;
use App\Infrastructure\Persistence\Eloquent\Models\LotterySchedule;
use App\Infrastructure\Persistence\Eloquent\Models\MethodVersion;
use Carbon\CarbonImmutable;

final class ResolveSignalSources
{
    /** @return array{draw: Draw, cutoff: CarbonImmutable, target_time: ?CarbonImmutable} */
    public function __invoke(MethodVersion $version, CarbonImmutable $date): array
    {
        $source = SourceDefinition::fromArray($version->source_definition);
        $relation = $source->relation;
        $sourceDate = $relation === SourceDayRelation::PreviousDay ? $date->subDay() : $date;
        $draws = Draw::query()->where('lottery_id', $source->lotteryId)->whereDate('draw_date_local', $sourceDate->toDateString())
            ->whereIn('status', ['confirmed', 'corrected'])->lockForUpdate()->get();
        if ($draws->isEmpty()) {
            throw new GenerationBlocked('skipped_missing_source', 'source_missing');
        }
        if ($draws->count() !== 1) {
            throw new GenerationBlocked('skipped_timing', 'source_ambiguous');
        }
        $draw = $draws->firstOrFail();
        $schedules = LotterySchedule::query()->where('lottery_id', $version->target_lottery_id)->where('is_active', true)
            ->where('weekday', $date->isoWeekday())->whereDate('effective_from', '<=', $date->toDateString())
            ->where(fn ($q) => $q->whereNull('effective_to')->orWhereDate('effective_to', '>=', $date->toDateString()))->get();
        $targetTime = $schedules->count() === 1 ? CarbonImmutable::parse($date->toDateString().' '.$schedules->firstOrFail()->draw_time_local, 'America/Santo_Domingo')->utc() : null;
        if ($relation === SourceDayRelation::SameDay && ($targetTime === null || $draw->drawn_at_utc === null)) {
            throw new GenerationBlocked('skipped_timing', 'source_timing_unknown');
        }
        // Without a target schedule, previous-day methods use the start of the target day, never its end.
        $cutoff = $targetTime ?? $date->utc();
        $now = CarbonImmutable::now('UTC');
        if ($cutoff->gt($now)) {
            $cutoff = $now;
        }
        if ($draw->drawn_at_utc !== null && CarbonImmutable::parse($draw->drawn_at_utc->format('Y-m-d H:i:s.u'), 'UTC')->gte($cutoff)) {
            throw new GenerationBlocked('skipped_timing', 'source_after_cutoff');
        }
        foreach (['received_at', 'confirmed_at'] as $field) {
            $instant = $draw->$field;
            if ($instant === null || CarbonImmutable::parse($instant->format('Y-m-d H:i:s.u'), 'UTC')->gte($cutoff)) {
                throw new GenerationBlocked('skipped_timing', 'source_not_available_at_cutoff');
            }
        }
        if (($draw->corrected_at !== null && CarbonImmutable::parse($draw->corrected_at->format('Y-m-d H:i:s.u'), 'UTC')->gte($cutoff))
            || $draw->corrections()->where('detected_at', '>=', $cutoff->format('Y-m-d H:i:s.u'))->exists()) {
            throw new GenerationBlocked('skipped_timing', 'source_corrected_after_cutoff');
        }

        return ['draw' => $draw, 'cutoff' => $cutoff, 'target_time' => $targetTime];
    }
}

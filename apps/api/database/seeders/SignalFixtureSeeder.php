<?php

namespace Database\Seeders;

use App\Infrastructure\Persistence\Eloquent\Models\Draw;
use App\Infrastructure\Persistence\Eloquent\Models\Lottery;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use LogicException;

/** Explicit local/CI fixture; never included in DatabaseSeeder. */
class SignalFixtureSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            throw new LogicException('Fixtures permitidos solo en local y testing.');
        }
        $this->call(MethodSeeder::class);
        DB::transaction(function (): void {
            foreach ([
                [15, '2001-01-01', '97', '32', 'phase02-p02-source'],
                [5, '2001-01-02', '07', '07', 'phase02-p02-result'],
            ] as [$externalId, $date, $p2, $p3, $externalDrawId]) {
                $lottery = Lottery::query()->where('external_id', $externalId)->firstOrFail();
                $identity = ['lottery_id' => $lottery->id, 'provider' => 'phase02-fixture', 'external_draw_id' => $externalDrawId];
                if (Draw::query()->where($identity)->exists()) {
                    continue;
                }
                if (Draw::query()->where('lottery_id', $lottery->id)->whereDate('draw_date_local', $date)->exists()) {
                    throw new LogicException('La fecha de fixture está ocupada; no se modifica el historial.');
                }
                Draw::query()->create($identity + [
                    'draw_date_local' => $date, 'scheduled_at_utc' => null, 'drawn_at_utc' => $date.' 18:00:00',
                    'received_at' => $date.' 18:01:00', 'confirmed_at' => $date.' 18:01:00', 'status' => 'confirmed',
                    'p1' => '14', 'p2' => $p2, 'p3' => $p3, 'source_hash' => hash('sha256', $externalDrawId), 'raw_payload' => ['fixture' => true],
                ]);
            }
        });
    }
}

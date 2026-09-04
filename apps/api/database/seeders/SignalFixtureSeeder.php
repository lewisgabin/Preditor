<?php

namespace Database\Seeders;

use App\Infrastructure\Persistence\Eloquent\Models\Draw;
use App\Infrastructure\Persistence\Eloquent\Models\Lottery;
use Illuminate\Database\Seeder;
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
        $lottery = Lottery::query()->where('external_id', 15)->firstOrFail();
        $identity = ['lottery_id' => $lottery->id, 'provider' => 'phase02-fixture', 'external_draw_id' => 'phase02-p02-source'];
        if (Draw::query()->where($identity)->exists()) {
            return;
        }
        if (Draw::query()->where('lottery_id', $lottery->id)->whereDate('draw_date_local', '2001-01-01')->exists()) {
            throw new LogicException('La fecha de fixture está ocupada; no se modifica el historial.');
        }
        Draw::query()->create($identity + [
            'draw_date_local' => '2001-01-01', 'scheduled_at_utc' => null, 'drawn_at_utc' => '2001-01-01 18:00:00',
            'received_at' => '2001-01-01 18:01:00', 'confirmed_at' => '2001-01-01 18:01:00', 'status' => 'confirmed',
            'p1' => '14', 'p2' => '97', 'p3' => '32', 'source_hash' => hash('sha256', 'phase02-p02-source'), 'raw_payload' => ['fixture' => true],
        ]);
    }
}

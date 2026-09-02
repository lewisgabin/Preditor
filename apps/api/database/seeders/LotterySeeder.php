<?php

namespace Database\Seeders;

use App\Infrastructure\Persistence\Eloquent\Models\Lottery;
use Illuminate\Database\Seeder;

class LotterySeeder extends Seeder
{
    public function run(): void
    {
        foreach ([
            [4, 'Lotería Nacional', 'loteria-nacional', 10], [5, 'Quiniela Leidsa', 'quiniela-leidsa', 20],
            [6, 'Quiniela Loteka', 'quiniela-loteka', 30], [12, 'Gana Más', 'gana-mas', 40],
            [13, 'Quiniela Real', 'quiniela-real', 50], [15, 'Quiniela LoteDom', 'quiniela-lotedom', 60],
            [18, 'La Primera Noche', 'la-primera-noche', 70], [20, 'La Primera Tarde', 'la-primera-tarde', 80],
            [21, 'La Suerte MD', 'la-suerte-md', 90], [29, 'La Suerte 6 PM', 'la-suerte-6-pm', 100],
        ] as [$externalId, $name, $slug, $sortOrder]) {
            Lottery::query()->updateOrCreate(['external_id' => $externalId], ['name' => $name, 'slug' => $slug, 'timezone' => 'America/Santo_Domingo', 'is_active' => true, 'sort_order' => $sortOrder]);
        }
    }
}

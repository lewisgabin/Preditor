<?php

namespace Database\Seeders;

use App\Infrastructure\Persistence\Eloquent\Models\Lottery;
use App\Infrastructure\Persistence\Eloquent\Models\Method;
use App\Infrastructure\Persistence\Eloquent\Models\MethodVersion;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use LogicException;

class MethodSeeder extends Seeder
{
    public function run(): void
    {
        $definitions = json_decode(file_get_contents(__DIR__.'/data/methods.json'), true, flags: JSON_THROW_ON_ERROR);
        DB::transaction(function () use ($definitions): void {
            foreach ($definitions as $definition) {
                $source = Lottery::query()->where('external_id', $definition['source'])->first();
                $target = Lottery::query()->where('external_id', $definition['target'])->first();
                if ($source === null || $target === null) {
                    throw new LogicException('Falta una lotería del catálogo para '.$definition['code']);
                }
                $relation = $definition['relation'] === 'same_day' ? 'del mismo día' : 'del día anterior';
                $method = Method::query()->firstOrCreate(['code' => $definition['code']], [
                    'name' => $source->name.' '.$definition['rule'].' → '.$target->name,
                    'description' => 'Método preconfigurado: tomar '.$source->name.' '.$relation.' y aplicar '.$definition['rule'].'.',
                    'category' => str_starts_with($definition['code'], 'P') ? 'primary' : 'alternative', 'is_active' => true,
                ]);
                MethodVersion::query()->firstOrCreate(['method_id' => $method->id, 'version' => 1], [
                    'target_lottery_id' => $target->id, 'source_definition' => ['lottery_id' => $source->id, 'relation' => $definition['relation']],
                    'operator_definition' => $definition['operator'], 'explanation_template' => $definition['rule'],
                    'valid_from' => '1900-01-01', 'is_active' => true,
                ]);
            }
        });
    }
}

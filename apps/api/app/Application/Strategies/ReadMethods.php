<?php

namespace App\Application\Strategies;

use App\Infrastructure\Persistence\Eloquent\Models\Lottery;
use App\Infrastructure\Persistence\Eloquent\Models\Method;
use Illuminate\Database\Eloquent\Collection;

final class ReadMethods
{
    /** @return Collection<int, Method> */
    public function all(): Collection
    {
        $methods = Method::query()->with(['versions' => fn ($q) => $q->orderByDesc('version'), 'versions.targetLottery'])->orderBy('code')->get();
        $this->loadSources($methods);

        return $methods;
    }

    public function one(Method $method): Method
    {
        $method->load(['versions' => fn ($q) => $q->orderByDesc('version'), 'versions.targetLottery']);
        $this->loadSources(new Collection([$method]));

        return $method;
    }

    /** @param Collection<int, Method> $methods */
    private function loadSources(Collection $methods): void
    {
        $ids = $methods->flatMap(fn (Method $method) => $method->versions->pluck('source_definition.lottery_id'))->unique();
        $lotteries = Lottery::query()->whereKey($ids)->get()->keyBy('id');
        foreach ($methods as $method) {
            foreach ($method->versions as $version) {
                $version->setRelation('sourceLottery', $lotteries->get($version->source_definition['lottery_id']));
            }
        }
    }
}

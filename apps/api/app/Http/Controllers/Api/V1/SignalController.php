<?php

namespace App\Http\Controllers\Api\V1;

use App\Application\Strategies\GenerateSignalsForDate;
use App\Application\Strategies\ReadSignals;
use App\Http\Requests\Strategies\GenerateSignalsRequest;
use App\Http\Resources\SignalResource;
use App\Infrastructure\Persistence\Eloquent\Models\Signal;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class SignalController
{
    public function index(Request $request, ReadSignals $read): AnonymousResourceCollection
    {
        $data = $request->validate(['date' => ['sometimes', 'date_format:Y-m-d']]);

        return SignalResource::collection($read->forDate($data['date'] ?? now('America/Santo_Domingo')->toDateString()));
    }

    public function show(Signal $signal, ReadSignals $read): SignalResource
    {
        $read->loadResults(new Collection([$signal]));

        return new SignalResource($signal);
    }

    public function generate(GenerateSignalsRequest $request, GenerateSignalsForDate $generate, ReadSignals $read): JsonResponse
    {
        $data = $request->validated();
        $summary = $generate($data['date'], $data['method_codes'] ?? []);
        $signals = new Collection($summary['signals']);
        $read->loadResults($signals);
        $summary['signals'] = SignalResource::collection($signals);

        return response()->json(['data' => $summary]);
    }
}

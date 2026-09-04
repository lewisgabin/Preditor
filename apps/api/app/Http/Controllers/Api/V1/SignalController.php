<?php

namespace App\Http\Controllers\Api\V1;

use App\Application\Strategies\GenerateSignalsForDate;
use App\Application\Strategies\ReadSignals;
use App\Http\Requests\Strategies\GenerateSignalsRequest;
use App\Http\Resources\SignalResource;
use App\Infrastructure\Persistence\Eloquent\Models\Signal;
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

    public function show(Signal $signal): SignalResource
    {
        return new SignalResource($signal->load('targetLottery'));
    }

    public function generate(GenerateSignalsRequest $request, GenerateSignalsForDate $generate): JsonResponse
    {
        $data = $request->validated();
        $summary = $generate($data['date'], $data['method_codes'] ?? []);
        $summary['signals'] = SignalResource::collection(collect($summary['signals'])->each->load('targetLottery'));

        return response()->json(['data' => $summary]);
    }
}

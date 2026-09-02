<?php

namespace App\Http\Controllers\Api\V1;

use App\Application\Draws\Queries\GetLottery;
use App\Application\Draws\Queries\ListLotteries;
use App\Http\Controllers\Controller;
use App\Http\Resources\LotteryResource;
use App\Infrastructure\Persistence\Eloquent\Models\Lottery;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class LotteryController extends Controller
{
    public function index(ListLotteries $listLotteries): AnonymousResourceCollection
    {
        return LotteryResource::collection($listLotteries->handle());
    }

    public function show(Lottery $lottery, GetLottery $getLottery): LotteryResource
    {
        return new LotteryResource($getLottery->handle($lottery));
    }
}

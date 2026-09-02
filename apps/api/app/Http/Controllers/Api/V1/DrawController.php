<?php

namespace App\Http\Controllers\Api\V1;

use App\Application\Draws\Queries\GetDraw;
use App\Application\Draws\Queries\ListDraws;
use App\Http\Controllers\Controller;
use App\Http\Requests\Draws\DrawIndexRequest;
use App\Http\Resources\DrawResource;
use App\Infrastructure\Persistence\Eloquent\Models\Draw;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class DrawController extends Controller
{
    public function index(DrawIndexRequest $request, ListDraws $listDraws): AnonymousResourceCollection
    {
        return DrawResource::collection($listDraws->handle($request->filters(), $request->perPage()));
    }

    public function show(Draw $draw, GetDraw $getDraw): DrawResource
    {
        return new DrawResource($getDraw->handle($draw));
    }
}

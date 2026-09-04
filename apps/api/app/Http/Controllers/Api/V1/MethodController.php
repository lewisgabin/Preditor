<?php

namespace App\Http\Controllers\Api\V1;

use App\Application\Strategies\ReadMethods;
use App\Http\Resources\MethodResource;
use App\Infrastructure\Persistence\Eloquent\Models\Method;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class MethodController
{
    public function index(ReadMethods $read): AnonymousResourceCollection
    {
        return MethodResource::collection($read->all());
    }

    public function show(Method $method, ReadMethods $read): MethodResource
    {
        return new MethodResource($read->one($method));
    }
}

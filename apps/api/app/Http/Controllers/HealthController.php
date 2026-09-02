<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Application\Health\GetHealthStatus;
use Illuminate\Http\JsonResponse;

class HealthController extends Controller
{
    public function __invoke(GetHealthStatus $getHealthStatus): JsonResponse
    {
        $health = $getHealthStatus();

        return response()->json($health, $health['status'] === 'ok' ? 200 : 503);
    }
}

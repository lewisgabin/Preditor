<?php

namespace App\Application\Draws\Contracts;

use App\Application\Draws\Data\DrawFetchRequest;
use App\Application\Draws\Data\DrawFetchResult;
use App\Application\Draws\Data\DrawProviderCapabilities;

interface LotteryDrawProvider
{
    public function capabilities(): DrawProviderCapabilities;

    public function fetch(DrawFetchRequest $request): DrawFetchResult;
}

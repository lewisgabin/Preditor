<?php

use App\Application\Draws\Data\NormalizedDrawData;
use App\Domain\Draws\Enums\DrawStatus;
use App\Domain\Draws\ValueObjects\LotteryNumber;

it('keeps normalized draw data independent from provider HTTP types', function (): void {
    $data = new NormalizedDrawData(4, 'fixture', 'draw-1', new DateTimeImmutable('2026-09-02'), null, null, new LotteryNumber('00'), new LotteryNumber('01'), new LotteryNumber('09'), DrawStatus::Confirmed, str_repeat('a', 64), ['result' => '00'], new DateTimeImmutable('2026-09-02T12:00:00Z'));

    expect($data->p1->value())->toBe('00')->and($data->rawPayload)->toBe(['result' => '00']);
});

<?php

namespace App\Application\Draws\Persistence;

use App\Infrastructure\Persistence\Eloquent\Models\Draw;
use App\Infrastructure\Persistence\Eloquent\Models\DrawQuarantine;

final readonly class PersistDrawResult
{
    private function __construct(
        public string $status,
        public ?Draw $draw = null,
        public ?DrawQuarantine $quarantine = null,
    ) {}

    public static function inserted(Draw $draw): self
    {
        return new self('inserted', $draw);
    }

    public static function unchanged(Draw $draw): self
    {
        return new self('unchanged', $draw);
    }

    public static function updated(Draw $draw): self
    {
        return new self('updated', $draw);
    }

    public static function quarantined(DrawQuarantine $quarantine): self
    {
        return new self('quarantined', null, $quarantine);
    }
}

<?php

namespace App\Console\Commands;

use App\Application\Strategies\GenerateSignalsForDate;
use App\Domain\Strategies\MethodCode;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class GenerateSignals extends Command
{
    protected $signature = 'signals:generate {--date=} {--method=} {--dry-run}';

    protected $description = 'Genera señales deterministas a partir de sorteos locales.';

    public function handle(GenerateSignalsForDate $generate): int
    {
        $date = $this->option('date') ?? now('America/Santo_Domingo')->toDateString();
        $code = $this->option('method');
        $validation = Validator::make(['date' => $date, 'method' => $code], ['date' => ['required', 'date_format:Y-m-d'], 'method' => ['nullable', Rule::enum(MethodCode::class)]]);
        if ($validation->fails()) {
            $this->error('Fecha o método inválido.');

            return self::FAILURE;
        }
        $summary = $generate($date, $code === null ? [] : [$code], (bool) $this->option('dry-run'));
        foreach ($summary['outcomes'] as $outcome) {
            $this->line($outcome['method_code'].': '.$outcome['outcome'].' '.($outcome['reason'] ?? ''));
        }
        foreach ($summary['signals'] as $signal) {
            $this->line($signal->calculation_snapshot['explanation']);
        }

        return $summary['error'] > 0 ? self::FAILURE : self::SUCCESS;
    }
}

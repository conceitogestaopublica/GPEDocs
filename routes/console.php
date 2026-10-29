<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Alerta individual de vencimento dos prazos lançados à mão nos processos (TR 4.32.17).
// Em produção precisa do cron do Laravel: * * * * * php artisan schedule:run
Illuminate\Support\Facades\Schedule::command('processos:alertar-prazos')->dailyAt('07:00')->timezone('America/Sao_Paulo');

<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Alerta individual de vencimento dos prazos lançados à mão nos processos (TR 4.32.17).
// Em produção precisa do cron do Laravel: * * * * * php artisan schedule:run
Illuminate\Support\Facades\Schedule::command('processos:alertar-prazos')->dailyAt('07:00')->timezone('America/Sao_Paulo');
// Memorandos e circulares com data de arquivamento automático (TR 4.46.12)
// Diário Oficial: edição ordinária do dia com os atos pendentes (TR 4.43.8)
Illuminate\Support\Facades\Schedule::command('diario:montar-ordinaria')->dailyAt('06:00')->timezone('America/Sao_Paulo');
// Tramita GOV.BR: recibos, recusas e recebidos (TR 4.28)
Illuminate\Support\Facades\Schedule::command('tramita:sincronizar')->everyFiveMinutes();
// Suporte: resolvido sem resposta do usuário é fechado (TR 4.26.8)
Illuminate\Support\Facades\Schedule::command('suporte:fechar-resolvidos')->hourly();
// Relatórios agendados entregues sozinhos (TR 4.25.15)
Illuminate\Support\Facades\Schedule::command('relatorios:agendados')->hourly();
Illuminate\Support\Facades\Schedule::command('comunicacao:arquivar-agendados')->dailyAt('00:15')->timezone('America/Sao_Paulo');

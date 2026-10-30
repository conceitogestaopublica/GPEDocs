<?php

/*
 * Envio de SMS (TR 4.11.1, 4.47.2). "log" só registra (desenvolvimento e enquanto não há
 * contrato); "http" manda POST JSON {to, message, from} com o token no cabeçalho
 * Authorization: Bearer — o formato da maioria dos gateways brasileiros, ajustável aqui.
 */
return [
    'driver'    => env('SMS_DRIVER', 'log'),
    'url'       => env('SMS_URL'),
    'token'     => env('SMS_TOKEN'),
    'remetente' => env('SMS_REMETENTE', 'GPEDocs'),
    'campos'    => ['para' => env('SMS_CAMPO_PARA', 'to'), 'texto' => env('SMS_CAMPO_TEXTO', 'message'), 'remetente' => env('SMS_CAMPO_REMETENTE', 'from')],
    'timeout'   => 10,
];

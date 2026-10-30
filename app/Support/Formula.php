<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Cálculo entre campos do formulário do assunto (TR 4.38.9): "{quantidade} * {valor_unitario}".
 * Só números, + - * /, parênteses e {campo} — avaliado por um parser próprio, sem eval.
 * Fórmula inválida ou divisão por zero dá null (o campo fica vazio, não derruba a abertura).
 */
final class Formula
{
    private array $tokens = [];
    private int $pos = 0;

    /** @param array<string, mixed> $valores */
    public static function avaliar(string $formula, array $valores): ?float
    {
        $expr = preg_replace_callback('/\{\s*([A-Za-z0-9_]+)\s*\}/', function ($m) use ($valores) {
            $v = $valores[$m[1]] ?? 0;
            $n = is_numeric($v) ? (float) $v : (float) str_replace(',', '.', preg_replace('/[^\d,.\-]/', '', (string) $v));

            return '(' . $n . ')';
        }, $formula);

        if ($expr === null || ! preg_match('/^[\d\s.+\-*\/()eE]+$/', $expr)) {
            return null;
        }
        preg_match_all('/\d+(?:\.\d+)?(?:[eE][+-]?\d+)?|[+\-*\/()]/', $expr, $m);

        $p = new self();
        $p->tokens = $m[0];
        try {
            $valor = $p->soma();
            if ($p->pos !== count($p->tokens)) {
                return null;
            }

            return is_finite($valor) ? round($valor, 6) : null;
        } catch (\DivisionByZeroError|\UnexpectedValueException) {
            return null;
        }
    }

    private function soma(): float
    {
        $v = $this->produto();
        while (in_array($this->tokens[$this->pos] ?? null, ['+', '-'], true)) {
            $op = $this->tokens[$this->pos++];
            $v = $op === '+' ? $v + $this->produto() : $v - $this->produto();
        }

        return $v;
    }

    private function produto(): float
    {
        $v = $this->fator();
        while (in_array($this->tokens[$this->pos] ?? null, ['*', '/'], true)) {
            $op = $this->tokens[$this->pos++];
            $d = $this->fator();
            if ($op === '/' && $d == 0.0) {
                throw new \DivisionByZeroError();
            }
            $v = $op === '*' ? $v * $d : $v / $d;
        }

        return $v;
    }

    private function fator(): float
    {
        $t = $this->tokens[$this->pos++] ?? null;
        if ($t === '-') {
            return -$this->fator();
        }
        if ($t === '(') {
            $v = $this->soma();
            if (($this->tokens[$this->pos++] ?? null) !== ')') {
                throw new \UnexpectedValueException();
            }

            return $v;
        }
        if ($t !== null && is_numeric($t)) {
            return (float) $t;
        }
        throw new \UnexpectedValueException();
    }
}

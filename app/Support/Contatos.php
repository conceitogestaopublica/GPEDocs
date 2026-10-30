<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\Portal\Cidadao;
use App\Services\Portal\CidadaoPortalService as Doc;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Contatos (TR 4.21): base única de pessoas físicas, jurídicas e setores de jurídica.
 * O cidadão do portal e o requerente do processo entram aqui sozinhos (pelo CPF/CNPJ);
 * regras de preenchimento por perfil, campos complementares e unificação de duplicados.
 */
final class Contatos
{
    public const TIPOS = ['pf' => 'Pessoa física', 'pj' => 'Pessoa jurídica', 'setor_pj' => 'Setor de pessoa jurídica'];

    /** Campos básicos que a regra do perfil pode tornar obrigatórios. */
    public const BASICOS = ['cpf' => 'CPF', 'cnpj' => 'CNPJ', 'email' => 'E-mail', 'celular' => 'Celular',
        'telefone' => 'Telefone', 'endereco' => 'Endereço', 'empresa_id' => 'Empresa (para setor de PJ)'];

    public const FORMATOS = ['texto' => 'Texto', 'numero' => 'Número', 'data' => 'Data', 'lista' => 'Lista de opções',
        'email' => 'E-mail', 'telefone' => 'Telefone', 'cpf_cnpj' => 'CPF ou CNPJ'];

    public const MAX_CAMPOS = 10;

    /** O contato que vale (segue a unificação). */
    public static function principal(int $id): int
    {
        for ($i = 0; $i < 10; $i++) {
            $destino = DB::table('contatos')->where('id', $id)->value('unificado_em_id');
            if (! $destino) {
                break;
            }
            $id = (int) $destino;
        }

        return $id;
    }

    public static function porDocumento(string $documento): ?int
    {
        $d = Doc::digitos($documento);
        if (! in_array(strlen($d), [11, 14], true)) {
            return null;
        }
        $id = DB::table('contatos')->whereNull('deleted_at')->whereNull('unificado_em_id')
            ->where(strlen($d) === 11 ? 'cpf' : 'cnpj', $d)->orderBy('id')->value('id');

        return $id ? (int) $id : null;
    }

    /** Cidadão do portal → contato (cria ou atualiza o ligado a ele). */
    public static function doCidadao(Cidadao $c): int
    {
        $doc = Doc::digitos($c->cpf);
        $dados = array_filter([
            'nome'     => $c->nome,
            'email'    => $c->email,
            'celular'  => $c->telefone,
            'endereco' => $c->endereco ? json_encode($c->endereco, JSON_UNESCAPED_UNICODE) : null,
        ], fn ($v) => $v !== null && $v !== '');

        $id = DB::table('contatos')->where('cidadao_id', $c->id)->value('id')
            ?? (strlen($doc) >= 11 ? self::porDocumento($doc) : null);
        if ($id) {
            DB::table('contatos')->where('id', $id)->update($dados + ['cidadao_id' => $c->id, 'updated_at' => now()]);

            return (int) $id;
        }

        return (int) DB::table('contatos')->insertGetId($dados + [
            'tipo' => strlen($doc) === 14 ? 'pj' : 'pf',
            strlen($doc) === 14 ? 'cnpj' : 'cpf' => $doc ?: null,
            'cidadao_id' => $c->id, 'origem' => 'portal', 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    /** Requerente do processo → contato (pelo CPF/CNPJ; sem documento, não cria). */
    public static function doRequerente(?string $documento, ?string $nome, ?string $email, ?string $telefone): ?int
    {
        $doc = Doc::digitos($documento);
        if (! in_array(strlen($doc), [11, 14], true) || ! Doc::documentoValido($doc)) {
            return null;
        }
        if ($id = self::porDocumento($doc)) {
            return $id;
        }

        return (int) DB::table('contatos')->insertGetId([
            'tipo' => strlen($doc) === 14 ? 'pj' : 'pf', strlen($doc) === 14 ? 'cnpj' : 'cpf' => $doc,
            'nome' => trim((string) $nome) ?: 'Sem nome', 'email' => $email ?: null, 'celular' => $telefone ?: null,
            'origem' => 'processo', 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    /** Liga a base aos cadastros que já existiam (cidadãos do portal e requerentes). */
    public static function sincronizarTudo(): array
    {
        $n = ['portal' => 0, 'processos' => 0];
        foreach (Cidadao::query()->orderBy('id')->cursor() as $c) {
            self::doCidadao($c);
            $n['portal']++;
        }
        DB::table('proc_processos')->whereNull('contato_id')->whereNotNull('requerente_cpf')->orderBy('id')
            ->select(['id', 'requerente_cpf', 'requerente_nome', 'requerente_email', 'requerente_telefone'])
            ->chunkById(500, function ($ps) use (&$n) {
                foreach ($ps as $p) {
                    if ($id = self::doRequerente($p->requerente_cpf, $p->requerente_nome, $p->requerente_email, $p->requerente_telefone)) {
                        DB::table('proc_processos')->where('id', $p->id)->update(['contato_id' => $id]);
                        $n['processos']++;
                    }
                }
            });

        return $n;
    }

    /** @return list<object> campos complementares ativos para o perfil e o lugar */
    public static function campos(?string $tipo = null, ?string $onde = null): array
    {
        return DB::table('contato_campos')->where('ativo', true)->orderBy('ordem')->orderBy('id')->get()
            ->filter(function ($c) use ($tipo, $onde) {
                $perfis = $c->perfis ? json_decode($c->perfis, true) : null;
                $noPerfil = ! $tipo || ! $perfis || in_array($tipo, $perfis, true);
                $noLugar = ! $onde || $c->onde === 'ambos' || $c->onde === $onde;

                return $noPerfil && $noLugar;
            })->values()->map(function ($c) {
                $c->opcoes = $c->opcoes ? json_decode($c->opcoes, true) : [];
                $c->perfis = $c->perfis ? json_decode($c->perfis, true) : null;

                return $c;
            })->all();
    }

    /** @return array<string, list<string>> tipo => campos básicos obrigatórios */
    public static function regras(): array
    {
        return DB::table('contato_regras')->pluck('obrigatorios', 'tipo')
            ->map(fn ($j) => json_decode($j, true) ?: [])->all();
    }

    /**
     * Confere o contato antes de gravar: regras do perfil, "ao menos um entre e-mail, CPF,
     * CNPJ e celular" (TR 4.21.4), documento válido, sem duplicar documento, complementares.
     *
     * @return array dados prontos para o banco
     */
    public static function validar(array $d, ?int $id = null, string $onde = 'interno'): array
    {
        $erros = [];
        $tipo = $d['tipo'] ?? null;
        if (! isset(self::TIPOS[$tipo])) {
            $erros['tipo'] = 'Escolha o tipo do contato.';
        }
        $nome = trim((string) ($d['nome'] ?? ''));
        if ($nome === '') {
            $erros['nome'] = 'Informe o nome.';
        }
        $cpf = Doc::digitos($d['cpf'] ?? '') ?: null;
        $cnpj = Doc::digitos($d['cnpj'] ?? '') ?: null;
        $email = mb_strtolower(trim((string) ($d['email'] ?? ''))) ?: null;
        $celular = trim((string) ($d['celular'] ?? '')) ?: null;
        $telefone = trim((string) ($d['telefone'] ?? '')) ?: null;
        $endereco = array_filter((array) ($d['endereco'] ?? []), fn ($v) => $v !== null && $v !== '') ?: null;
        $empresa = ! empty($d['empresa_id']) ? (int) $d['empresa_id'] : null;

        if ($tipo === 'pf') {
            $cnpj = null;
        } else {
            $cpf = null;
        }
        if ($cpf && (strlen($cpf) !== 11 || ! Doc::documentoValido($cpf))) {
            $erros['cpf'] = 'CPF inválido.';
        }
        if ($cnpj && (strlen($cnpj) !== 14 || ! Doc::cnpjValido($cnpj))) {
            $erros['cnpj'] = 'CNPJ inválido.';
        }
        if ($email && ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $erros['email'] = 'E-mail inválido.';
        }
        if ($tipo !== 'setor_pj') {
            $empresa = null;
        } elseif ($empresa && ! DB::table('contatos')->where('id', $empresa)->where('tipo', 'pj')->whereNull('deleted_at')->exists()) {
            $erros['empresa_id'] = 'Escolha uma pessoa jurídica.';
        }
        if (! $cpf && ! $cnpj && ! $email && ! $celular) {
            $erros['email'] = 'Informe ao menos um: e-mail, CPF, CNPJ ou celular.';
        }

        $valores = compact('cpf', 'cnpj', 'email', 'celular', 'telefone', 'endereco') + ['empresa_id' => $empresa];
        foreach ((self::regras()[$tipo] ?? []) as $campo) {
            if (array_key_exists($campo, $valores) && empty($valores[$campo]) && ! isset($erros[$campo])) {
                $erros[$campo] = (self::BASICOS[$campo] ?? $campo) . ' é obrigatório para ' . mb_strtolower(self::TIPOS[$tipo]) . '.';
            }
        }

        // Mesmo documento já cadastrado (TR 4.21.13 trata os que já estão duplicados)
        foreach (['cpf' => $cpf, 'cnpj' => $cnpj] as $col => $v) {
            if ($v && ! isset($erros[$col])) {
                $outro = DB::table('contatos')->where($col, $v)->whereNull('deleted_at')->whereNull('unificado_em_id')
                    ->when($id, fn ($q) => $q->where('id', '<>', $id))->first(['id', 'nome']);
                if ($outro) {
                    $erros[$col] = strtoupper($col) . " já cadastrado: {$outro->nome} (contato #{$outro->id}).";
                }
            }
        }

        [$complementares, $errosComplementares] = self::complementares((string) $tipo, (array) ($d['campos'] ?? []), $onde);
        // Os já preenchidos em campos de outro lugar (ex.: só no portal) ficam guardados
        if ($id && ($antigos = DB::table('contatos')->where('id', $id)->value('campos'))) {
            $complementares += json_decode($antigos, true) ?: [];
        }
        $erros += $errosComplementares;

        if ($erros) {
            throw ValidationException::withMessages($erros);
        }

        return [
            'tipo' => $tipo, 'nome' => $nome, 'cpf' => $cpf, 'cnpj' => $cnpj, 'email' => $email, 'celular' => $celular,
            'telefone' => $telefone, 'endereco' => $endereco ? json_encode($endereco, JSON_UNESCAPED_UNICODE) : null,
            'empresa_id' => $empresa, 'observacao' => trim((string) ($d['observacao'] ?? '')) ?: null,
            'campos' => $complementares ? json_encode($complementares, JSON_UNESCAPED_UNICODE) : null,
        ];
    }

    /**
     * Campos complementares do perfil pedidos naquele lugar (cadastro interno ou autocadastro).
     *
     * @return array{0: array<int, mixed>, 1: array<string, string>} valores e erros ("campos.{id}")
     */
    public static function complementares(string $tipo, array $valores, string $onde): array
    {
        $ok = [];
        $erros = [];
        foreach (self::campos($tipo, $onde) as $c) {
            $v = $valores[$c->id] ?? $valores[(string) $c->id] ?? null;
            $v = is_string($v) ? trim($v) : $v;
            if ($v === null || $v === '') {
                if ($c->obrigatorio) {
                    $erros["campos.{$c->id}"] = "{$c->nome} é obrigatório.";
                }

                continue;
            }
            $valido = match ($c->formato) {
                'numero'   => is_numeric(str_replace(',', '.', (string) $v)),
                'data'     => (bool) strtotime((string) $v),
                'email'    => (bool) filter_var($v, FILTER_VALIDATE_EMAIL),
                'lista'    => in_array($v, $c->opcoes, true),
                'cpf_cnpj' => Doc::documentoValido((string) $v),
                default    => mb_strlen((string) $v) <= 500,
            };
            if (! $valido) {
                $erros["campos.{$c->id}"] = "{$c->nome}: valor inválido.";
            }
            $ok[$c->id] = $v;
        }

        return [$ok, $erros];
    }

    /** Guarda no contato do cidadão os complementares que vieram do autocadastro do portal. */
    public static function guardarDoPortal(Cidadao $c, array $valores): void
    {
        $id = DB::table('contatos')->where('cidadao_id', $c->id)->value('id') ?? self::doCidadao($c);
        $atuais = json_decode((string) DB::table('contatos')->where('id', $id)->value('campos'), true) ?: [];
        DB::table('contatos')->where('id', $id)->update([
            'campos' => json_encode($valores + $atuais, JSON_UNESCAPED_UNICODE), 'updated_at' => now(),
        ]);
    }

    /**
     * Unifica duplicados no principal (TR 4.21.13): completa o que falta, leva processos,
     * procurações, listas, setores e o cadastro do portal; os outros ficam apontando para ele.
     */
    public static function unificar(int $principalId, array $outros): int
    {
        $outros = array_values(array_diff(array_map('intval', $outros), [$principalId]));

        DB::transaction(function () use ($principalId, $outros) {
            $p = DB::table('contatos')->where('id', $principalId)->lockForUpdate()->first();
            $completar = [];
            $campos = $p->campos ? json_decode($p->campos, true) : [];
            foreach (DB::table('contatos')->whereIn('id', $outros)->lockForUpdate()->get() as $o) {
                foreach (['cpf', 'cnpj', 'email', 'celular', 'telefone', 'endereco', 'empresa_id', 'observacao'] as $col) {
                    if (empty($p->{$col}) && empty($completar[$col]) && ! empty($o->{$col})) {
                        $completar[$col] = $o->{$col};
                    }
                }
                $campos += $o->campos ? json_decode($o->campos, true) : [];
                if (! $p->cidadao_id && empty($completar['cidadao_id']) && $o->cidadao_id) {
                    $completar['cidadao_id'] = $o->cidadao_id;
                }
            }
            DB::table('contatos')->whereIn('id', $outros)->update(['cidadao_id' => null]);

            DB::table('proc_processos')->whereIn('contato_id', $outros)->update(['contato_id' => $principalId]);
            DB::table('proc_processos')->whereIn('procurador_contato_id', $outros)->update(['procurador_contato_id' => $principalId]);
            DB::table('contato_procuracoes')->whereIn('outorgante_id', $outros)->update(['outorgante_id' => $principalId]);
            DB::table('contato_procuracoes')->whereIn('procurador_id', $outros)->update(['procurador_id' => $principalId]);
            DB::table('contato_procuracoes')->whereColumn('outorgante_id', 'procurador_id')->delete();
            DB::table('contatos')->whereIn('empresa_id', $outros)->update(['empresa_id' => $principalId]);
            foreach (DB::table('contato_lista_itens')->whereIn('contato_id', $outros)->pluck('lista_id') as $lista) {
                DB::table('contato_lista_itens')->insertOrIgnore(['lista_id' => $lista, 'contato_id' => $principalId]);
            }
            DB::table('contato_lista_itens')->whereIn('contato_id', $outros)->delete();

            DB::table('contatos')->where('id', $principalId)->update($completar + [
                'campos' => $campos ? json_encode($campos, JSON_UNESCAPED_UNICODE) : null, 'updated_at' => now(),
            ]);
            DB::table('contatos')->whereIn('id', $outros)->update(['unificado_em_id' => $principalId, 'deleted_at' => now(), 'updated_at' => now()]);
            self::registrar($principalId, 'unificacao', ['unificados' => $outros]);
            foreach ($outros as $o) {
                self::registrar($o, 'unificado_em', ['principal' => $principalId]);
            }
        });

        return count($outros);
    }

    public static function registrar(int $contatoId, string $acao, array $detalhes = []): void
    {
        DB::table('contato_historico')->insert([
            'contato_id' => $contatoId, 'acao' => $acao, 'detalhes' => json_encode($detalhes, JSON_UNESCAPED_UNICODE),
            'usuario_id' => Auth::id(), 'created_at' => now(),
        ]);
    }

    /** Documento com máscara, para tela e CSV. */
    public static function documento(?string $cpf, ?string $cnpj): ?string
    {
        if ($cpf && strlen($cpf) === 11) {
            return substr($cpf, 0, 3) . '.' . substr($cpf, 3, 3) . '.' . substr($cpf, 6, 3) . '-' . substr($cpf, 9);
        }
        if ($cnpj && strlen($cnpj) === 14) {
            return substr($cnpj, 0, 2) . '.' . substr($cnpj, 2, 3) . '.' . substr($cnpj, 5, 3) . '/' . substr($cnpj, 8, 4) . '-' . substr($cnpj, 12);
        }

        return null;
    }
}

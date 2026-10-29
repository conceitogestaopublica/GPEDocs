<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Notificacao;
use App\Models\Processo\Processo;
use App\Models\Processo\ProcessoAnexo;
use App\Models\Processo\ProcessoComentario;
use App\Models\Processo\ProcessoHistorico;
use App\Models\Processo\Tramitacao;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class TramitacaoController extends Controller
{
    public function inbox(): Response
    {
        $tramitacoes = Tramitacao::where('destinatario_id', Auth::id())
            ->whereIn('status', ['pendente', 'recebido', 'em_analise'])
            ->with(['processo.tipoProcesso', 'remetente'])
            ->orderByDesc('created_at')
            ->paginate(20);

        return Inertia::render('GED/Processos/Inbox', [
            'tramitacoes' => $tramitacoes,
        ]);
    }

    public function receber(Request $request, $id)
    {
        try {
            DB::beginTransaction();

            $tramitacao = Tramitacao::lockForUpdate()->findOrFail($id);
            \App\Services\AcaoNoProcesso::exigirAtivoNaTramitacao($tramitacao, Auth::user());
            if ($tramitacao->status !== 'pendente') {
                abort(409, 'Esta etapa já foi recebida.');
            }
            $tramitacao->update([
                'status'      => 'recebido',
                'recebido_por'=> Auth::id(),
                'recebido_em' => now(),
            ]);

            $tramitacao->processo->update([
                'status' => 'em_tramitacao',
            ]);

            ProcessoHistorico::create([
                'processo_id' => $tramitacao->processo_id,
                'usuario_id'  => Auth::id(),
                'acao'        => 'recebimento',
                'detalhes'    => [
                    'tramitacao_id' => $tramitacao->id,
                    'etapa'         => $tramitacao->tipoEtapa?->nome,
                ],
                'ip'         => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);

            DB::commit();

            return redirect()->back()->with('success', 'Processo recebido com sucesso.');
        } catch (\Exception $e) {
            DB::rollBack();

            return redirect()->back()->with('error', 'Erro ao receber processo: ' . $e->getMessage());
        }
    }

    public function despachar(Request $request, $id)
    {
        $request->validate([
            'destinatario_id' => ['nullable', 'integer', 'exists:users,id'],
            'despacho'        => ['required', 'string'],
            'setor_destino'   => ['required', 'integer', 'exists:ug_organograma,id'],
            // Outros setores que tramitam a parte deles em paralelo (TR 4.9.4)
            'outros_setores'   => ['nullable', 'array', 'max:20'],
            'outros_setores.*' => ['integer', 'distinct', 'exists:ug_organograma,id'],
            // Setores que só recebem cópia, para ciência (TR 4.9.14)
            'copias'           => ['nullable', 'array', 'max:20'],
            'copias.*'         => ['integer', 'distinct', 'exists:ug_organograma,id'],
            // Etapa do fluxo para onde vai: id de uma das permitidas, ou 'manter' (fica na atual)
            'etapa_destino'    => ['nullable', 'string', 'max:20'],
            // Manifestação do setor que despacha (TR 4.38.14)
            'manifestacao'     => ['nullable', 'in:deferido,indeferido'],
            'files'           => ['nullable', 'array'],
            'files.*'         => ['file', 'max:51200'],
        ]);

        // Resolve nome do setor a partir do id
        $unidadeDestino = \App\Models\UgOrganograma::find($request->input('setor_destino'));
        $setorDestinoNome = $unidadeDestino?->nome;
        $setorDestinoId   = (int) $request->input('setor_destino');

        try {
            DB::beginTransaction();

            $tramitacaoAtual = Tramitacao::with('processo.tipoProcesso.etapas')->lockForUpdate()->findOrFail($id);
            \App\Services\AcaoNoProcesso::exigirAtivoNaTramitacao($tramitacaoAtual, Auth::user());
            $processo = $tramitacaoAtual->processo;

            // Finalizar tramitacao atual
            $tramitacaoAtual->update([
                'status'        => 'despachado',
                // Despachar etapa ainda pendente vale como recebimento por quem despacha.
                'recebido_por'  => $tramitacaoAtual->recebido_por ?? Auth::id(),
                'recebido_em'   => $tramitacaoAtual->recebido_em ?? now(),
                'despachado_em' => now(),
                'despacho'      => $this->textoDoDespacho($request, $processo, $tramitacaoAtual),
                'manifestacao'  => $request->input('manifestacao') ?: null,
            ]);

            // Etapa de destino: o fluxo do tipo de processo é o da frente principal, sem pular
            // etapa obrigatória e só para os setores dela (TR 4.38.19, 4.38.20)
            $proximaEtapa = null;
            if ($tramitacaoAtual->frente === 1) {
                $fluxo = \App\Services\FluxoDoProcesso::conferirDespacho(
                    $processo, $tramitacaoAtual->tipo_etapa_id, $request->input('etapa_destino'), $setorDestinoId);
                if ($fluxo['erro']) {
                    DB::rollBack();

                    return redirect()->back()->withErrors(['setor_destino' => $fluxo['erro']])->with('error', $fluxo['erro']);
                }
                $proximaEtapa = $fluxo['etapa'];
            }

            // Criar nova tramitacao
            $novaTramitacao = Tramitacao::create([
                'processo_id'        => $processo->id,
                'tipo_etapa_id'      => $proximaEtapa?->id,
                'ordem'              => $tramitacaoAtual->ordem + 1,
                'setor_origem'       => $tramitacaoAtual->setor_destino,
                'setor_destino'      => $setorDestinoNome,
                'destino_unidade_id' => $setorDestinoId,
                'remetente_id'       => Auth::id(),
                'destinatario_id'    => $request->input('destinatario_id'),
                'status'             => 'pendente',
                'sla_horas'          => $proximaEtapa?->sla_horas ?? $processo->tipoProcesso->sla_padrao_horas,
                'prazo'              => \App\Support\Expediente::prazo($processo->tipoProcesso, $proximaEtapa?->sla_horas ?? $processo->tipoProcesso->sla_padrao_horas),
                'frente'             => $tramitacaoAtual->frente,
            ]);

            if ($tramitacaoAtual->frente === 1) {
                $processo->update(['etapa_atual_id' => $novaTramitacao->id]);
            }

            // Outros setores (frentes novas) e cópias: mesma origem e despacho
            $paralelas = $this->abrirParalelas($processo, $tramitacaoAtual, $novaTramitacao,
                array_diff(array_map('intval', $request->input('outros_setores', [])), [$setorDestinoId]),
                array_map('intval', $request->input('copias', [])));

            // Armazenar anexos
            if ($request->hasFile('files')) {
                foreach ($request->file('files') as $file) {
                    $path = $file->store(\App\Tenant\TenantStorage::pasta('processos'), 'documentos');

                    ProcessoAnexo::create([
                        'processo_id'   => $processo->id,
                        'tramitacao_id' => $novaTramitacao->id,
                        'nome'          => $file->getClientOriginalName(),
                        'arquivo_path'  => $path,
                        'tamanho'       => $file->getSize(),
                        'mime_type'     => $file->getMimeType(),
                        'hash_sha256'   => hash_file('sha256', $file->getRealPath()),
                        'enviado_por'   => Auth::id(),
                    ]);
                }
            }

            ProcessoHistorico::create([
                'processo_id' => $processo->id,
                'usuario_id'  => Auth::id(),
                'acao'        => 'despacho',
                'detalhes'    => [
                    'de_tramitacao'   => $tramitacaoAtual->id,
                    'para_tramitacao' => $novaTramitacao->id,
                    'destinatario_id' => $request->input('destinatario_id'),
                    'setor_destino'   => $setorDestinoNome,
                    'destino_unidade_id' => $setorDestinoId,
                    'despacho'        => $this->textoDoDespacho($request, $processo, $tramitacaoAtual),
                    'outros_setores'  => $paralelas['frentes'],
                    'copias'          => $paralelas['copias'],
                ],
                'ip'         => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);

            // Notifica destinatario especifico OU todos os usuarios do setor de destino
            $usuariosNotificar = [];
            if ($request->filled('destinatario_id')) {
                $usuariosNotificar[] = (int) $request->input('destinatario_id');
            } elseif ($setorDestinoId) {
                $usuariosNotificar = \App\Support\Setores::servidores($setorDestinoId);
            }
            $this->avisar($usuariosNotificar, $processo, 'Processo despachado para voce', 'foi despachado para você.');
            foreach ($paralelas['avisar'] as [$ids, $copia]) {
                $this->avisar($ids, $processo,
                    $copia ? 'Cópia de processo para ciência' : 'Processo despachado para o seu setor',
                    $copia ? 'foi enviado em cópia ao seu setor, para ciência.' : 'chegou ao seu setor para tratar a parte dele.');
            }

            DB::commit();

            return redirect()->back()->with('success', 'Processo despachado com sucesso.');
        } catch (\Exception $e) {
            DB::rollBack();

            return redirect()->back()->with('error', 'Erro ao despachar processo: ' . $e->getMessage());
        }
    }

    public function devolver(Request $request, $id)
    {
        $request->validate([
            'despacho' => ['nullable', 'string'],
        ]);

        try {
            DB::beginTransaction();

            $tramitacaoAtual = Tramitacao::lockForUpdate()->findOrFail($id);
            \App\Services\AcaoNoProcesso::exigirAtivoNaTramitacao($tramitacaoAtual, Auth::user());
            $processo = $tramitacaoAtual->processo;

            // Finalizar tramitacao atual
            $tramitacaoAtual->update([
                'status'        => 'devolvido',
                'despachado_em' => now(),
                'despacho'      => $this->textoDoDespacho($request, $processo, $tramitacaoAtual),
            ]);

            // Criar nova tramitacao devolvendo ao remetente anterior
            $novaTramitacao = Tramitacao::create([
                'processo_id'     => $processo->id,
                'tipo_etapa_id'   => $tramitacaoAtual->tipo_etapa_id,
                'ordem'           => $tramitacaoAtual->ordem + 1,
                'setor_origem'    => $tramitacaoAtual->setor_destino,
                'setor_destino'   => $tramitacaoAtual->setor_origem,
                'remetente_id'    => Auth::id(),
                'destinatario_id' => $tramitacaoAtual->remetente_id,
                'status'          => 'pendente',
                'sla_horas'       => $tramitacaoAtual->sla_horas,
                'prazo'           => \App\Support\Expediente::prazo($processo->tipoProcesso, $tramitacaoAtual->sla_horas ?? 48),
                'frente'          => $tramitacaoAtual->frente,
            ]);

            if ($tramitacaoAtual->frente === 1) {
                $processo->update(['etapa_atual_id' => $novaTramitacao->id]);
            }

            ProcessoHistorico::create([
                'processo_id' => $processo->id,
                'usuario_id'  => Auth::id(),
                'acao'        => 'devolucao',
                'detalhes'    => [
                    'de_tramitacao'   => $tramitacaoAtual->id,
                    'para_tramitacao' => $novaTramitacao->id,
                    'destinatario_id' => $tramitacaoAtual->remetente_id,
                    'despacho'        => $this->textoDoDespacho($request, $processo, $tramitacaoAtual),
                ],
                'ip'         => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);

            Notificacao::create([
                'usuario_id'     => $tramitacaoAtual->remetente_id,
                'tipo'           => 'processo',
                'titulo'         => 'Processo devolvido',
                'mensagem'       => "Processo {$processo->numero_protocolo} - {$processo->assunto} foi devolvido para você.",
                'referencia_tipo'=> 'processo',
                'referencia_id'  => $processo->id,
                'lida'           => false,
            ]);

            DB::commit();

            return redirect()->back()->with('success', 'Processo devolvido com sucesso.');
        } catch (\Exception $e) {
            DB::rollBack();

            return redirect()->back()->with('error', 'Erro ao devolver processo: ' . $e->getMessage());
        }
    }

    /**
     * Abre as frentes de outros setores e as cópias de um despacho.
     *
     * @param  list<int>  $setores  frentes novas (cada uma tramita a sua parte)
     * @param  list<int>  $copias   só ciência
     * @return array{frentes: list<string>, copias: list<string>, avisar: list<array{0: list<int>, 1: bool}>}
     */
    private function abrirParalelas(\App\Models\Processo\Processo $processo, Tramitacao $origem, Tramitacao $nova, array $setores, array $copias): array
    {
        $resultado = ['frentes' => [], 'copias' => [], 'avisar' => []];
        $proximaFrente = (int) Tramitacao::where('processo_id', $processo->id)->max('frente') + 1;
        $unidades = \App\Models\UgOrganograma::whereIn('id', [...$setores, ...$copias])->pluck('nome', 'id');
        $sla = $processo->tipoProcesso->sla_padrao_horas;

        foreach ([[$setores, false], [$copias, true]] as [$ids, $copia]) {
            foreach (array_unique($ids) as $unidadeId) {
                Tramitacao::create([
                    'processo_id'        => $processo->id,
                    'ordem'              => $nova->ordem,
                    'setor_origem'       => $nova->setor_origem,
                    'setor_destino'      => $unidades[$unidadeId] ?? '-',
                    'destino_unidade_id' => $unidadeId,
                    'remetente_id'       => Auth::id(),
                    'status'             => 'pendente',
                    'despacho'           => $origem->despacho,
                    'sla_horas'          => $copia ? null : $sla,
                    'prazo'              => $copia ? null : \App\Support\Expediente::prazo($processo->tipoProcesso, $sla),
                    'frente'             => $copia ? $origem->frente : $proximaFrente++,
                    'modo'               => $copia ? 'copia' : 'tramite',
                ]);
                $resultado[$copia ? 'copias' : 'frentes'][] = $unidades[$unidadeId] ?? (string) $unidadeId;
                $resultado['avisar'][] = [\App\Support\Setores::servidores($unidadeId), $copia];
            }
        }

        return $resultado;
    }

    /** @param  list<int>  $usuarios */
    private function avisar(array $usuarios, \App\Models\Processo\Processo $processo, string $titulo, string $complemento): void
    {
        foreach (array_unique($usuarios) as $uid) {
            Notificacao::create([
                'usuario_id'      => (int) $uid,
                'tipo'            => 'processo',
                'titulo'          => $titulo,
                'mensagem'        => "Processo {$processo->numero_protocolo} - {$processo->assunto} {$complemento}",
                'referencia_tipo' => 'processo',
                'referencia_id'   => $processo->id,
                'lida'            => false,
            ]);
        }
    }

    /**
     * O setor de uma frente que não é a principal resolve a parte dele (TR 4.9.4). Quando o
     * último resolve, quem está na frente principal é avisado de que já pode concluir.
     */
    public function resolver(Request $request, $id)
    {
        $request->validate([
            'parecer'      => ['required', 'string', 'max:5000'],
            'manifestacao' => ['nullable', 'in:deferido,indeferido'],
        ]);

        $tramitacao = Tramitacao::findOrFail($id);
        \App\Services\AcaoNoProcesso::exigirAtivoNaTramitacao($tramitacao, Auth::user());
        if ($tramitacao->frente === 1) {
            return redirect()->back()->with('error', 'O fluxo principal é encerrado em "Concluir", não aqui.');
        }
        $processo = $tramitacao->processo;

        DB::transaction(function () use ($request, $tramitacao, $processo) {
            // Dois cliques ou duas pessoas ao mesmo tempo (TR 4.9.11): só a primeira passa
            $tramitacao = Tramitacao::lockForUpdate()->findOrFail($tramitacao->id);
            \App\Services\AcaoNoProcesso::exigirAtivoNaTramitacao($tramitacao, Auth::user());
            $tramitacao->update([
                'status'        => 'resolvido',
                'parecer'       => $request->input('parecer'),
                'manifestacao'  => $request->input('manifestacao') ?: null,
                'recebido_por'  => $tramitacao->recebido_por ?? Auth::id(),
                'recebido_em'   => $tramitacao->recebido_em ?? now(),
                'resolvido_em'  => now(),
                'resolvido_por' => Auth::id(),
            ]);

            ProcessoHistorico::create([
                'processo_id' => $processo->id,
                'usuario_id'  => Auth::id(),
                'acao'        => 'resolucao_setor',
                'detalhes'    => ['tramitacao_id' => $tramitacao->id, 'setor' => $tramitacao->setor_destino, 'parecer' => $request->input('parecer')],
                'ip'          => $request->ip(),
                'user_agent'  => $request->userAgent(),
            ]);

            $faltam = \App\Services\AcaoNoProcesso::etapasAtivas($processo)->filter(fn ($t) => $t->frente !== 1);
            $principal = \App\Services\AcaoNoProcesso::etapaAtual($processo);
            if ($faltam->isEmpty() && $principal && $principal->frente === 1) {
                $this->avisar(
                    $principal->destinatario_id ? [$principal->destinatario_id] : \App\Support\Setores::servidores((int) $principal->destino_unidade_id),
                    $processo, 'Todos os setores resolveram', 'teve a parte de todos os setores resolvida e já pode ser concluído.');
            }
        });

        return redirect()->back()->with('success', "Parte do setor {$tramitacao->setor_destino} resolvida.");
    }

    /** Estágio da etapa no setor (em análise, aguardando documentos…) — TR 4.9.4. */
    public function estagio(Request $request, $id)
    {
        $request->validate(['estagio' => ['nullable', 'string', 'max:60']]);

        $tramitacao = Tramitacao::findOrFail($id);
        \App\Services\AcaoNoProcesso::exigirAtivoNaTramitacao($tramitacao, Auth::user());
        $estagio = trim((string) $request->input('estagio')) ?: null;
        $tramitacao->update(['estagio' => $estagio]);

        ProcessoHistorico::create([
            'processo_id' => $tramitacao->processo_id,
            'usuario_id'  => Auth::id(),
            'acao'        => 'estagio',
            'detalhes'    => ['tramitacao_id' => $tramitacao->id, 'setor' => $tramitacao->setor_destino, 'estagio' => $estagio],
            'ip'          => $request->ip(),
            'user_agent'  => $request->userAgent(),
        ]);

        return redirect()->back()->with('success', $estagio ? "Estágio: {$estagio}." : 'Estágio retirado.');
    }

    /** Ciência da cópia (TR 4.9.14): sai da caixa do setor; o processo não esperava por ela. */
    public function ciente(Request $request, $id)
    {
        $copia = Tramitacao::findOrFail($id);
        if (! $copia->processo) {
            abort(404);
        }
        if ($copia->modo !== 'copia' || $copia->status !== 'pendente') {
            return redirect()->back()->with('error', 'Esta cópia já teve ciência.');
        }
        abort_unless(\App\Services\AcaoNoProcesso::ativoNaEtapa($copia, Auth::user()), 403, 'A cópia não foi enviada para você nem para um setor seu.');

        // Atualização condicional: dois cliques ao mesmo tempo registram uma ciência só
        $marcou = Tramitacao::whereKey($copia->id)->where('status', 'pendente')
            ->update(['status' => 'ciente', 'recebido_por' => Auth::id(), 'recebido_em' => now(), 'lida_em' => $copia->lida_em ?? now()]);
        if (! $marcou) {
            return redirect()->back()->with('error', 'Esta cópia já teve ciência.');
        }
        ProcessoHistorico::create([
            'processo_id' => $copia->processo_id,
            'usuario_id'  => Auth::id(),
            'acao'        => 'ciencia_copia',
            'detalhes'    => ['tramitacao_id' => $copia->id, 'setor' => $copia->setor_destino],
            'ip'          => $request->ip(),
            'user_agent'  => $request->userAgent(),
        ]);

        return redirect()->back()->with('success', 'Ciência registrada.');
    }

    /**
     * "Aos cuidados" (TR 4.9.13): quem está na etapa do setor a atribui a si ou a um
     * colega do setor, sem tramitar — ela sai da caixa do setor e vai para a caixa
     * pessoal dele. Sem usuário, volta para a caixa do setor.
     */
    public function aosCuidados(Request $request, $id)
    {
        $request->validate(['usuario_id' => ['nullable', 'integer', 'exists:users,id']]);

        $tramitacao = Tramitacao::findOrFail($id);
        \App\Services\AcaoNoProcesso::exigirAtivoNaTramitacao($tramitacao, Auth::user());
        if (! $tramitacao->destino_unidade_id) {
            return redirect()->back()->with('error', 'Só a etapa endereçada a um setor pode ficar aos cuidados de alguém.');
        }

        $novo = $request->filled('usuario_id') ? \App\Models\User::find((int) $request->input('usuario_id')) : null;
        if ($novo && ! \App\Support\Setores::atende($novo, $tramitacao->destino_unidade_id)) {
            return redirect()->back()->with('error', "{$novo->name} não atende o setor {$tramitacao->setor_destino}.");
        }
        if ($novo?->id === $tramitacao->destinatario_id) {
            return redirect()->back();
        }

        DB::transaction(function () use ($request, $tramitacao, $novo) {
            $tramitacao = Tramitacao::lockForUpdate()->findOrFail($tramitacao->id);
            \App\Services\AcaoNoProcesso::exigirAtivoNaTramitacao($tramitacao, Auth::user());
            $anterior = $tramitacao->destinatario_id;
            $tramitacao->update(['destinatario_id' => $novo?->id]);
            $processo = $tramitacao->processo;

            ProcessoHistorico::create([
                'processo_id' => $processo->id,
                'usuario_id'  => Auth::id(),
                'acao'        => 'aos_cuidados',
                'detalhes'    => [
                    'tramitacao_id' => $tramitacao->id,
                    'setor'         => $tramitacao->setor_destino,
                    'de_usuario_id' => $anterior,
                    'para_usuario_id' => $novo?->id,
                    'para'          => $novo?->name ?? 'caixa do setor',
                ],
                'ip'         => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);

            if ($novo && $novo->id !== Auth::id()) {
                Notificacao::create([
                    'usuario_id'      => $novo->id,
                    'tipo'            => 'processo',
                    'titulo'          => 'Processo aos seus cuidados',
                    'mensagem'        => Auth::user()->name . " deixou o processo {$processo->numero_protocolo} - {$processo->assunto} aos seus cuidados.",
                    'referencia_tipo' => 'processo',
                    'referencia_id'   => $processo->id,
                    'lida'            => false,
                ]);
            }
        });

        return redirect()->back()->with('success', $novo
            ? ($novo->id === Auth::id() ? 'O processo ficou aos seus cuidados.' : "O processo ficou aos cuidados de {$novo->name}.")
            : 'O processo voltou para a caixa do setor.');
    }

    public function comentar(Request $request, $id)
    {
        $request->validate([
            'texto'   => ['required', 'string'],
            'interno' => ['nullable', 'boolean'],
        ]);

        try {
            DB::beginTransaction();

            $tramitacao = Tramitacao::findOrFail($id);

            ProcessoComentario::create([
                'processo_id'   => $tramitacao->processo_id,
                'tramitacao_id' => $tramitacao->id,
                'usuario_id'    => Auth::id(),
                'texto'         => $request->input('texto'),
                'interno'       => $request->boolean('interno', false),
            ]);

            ProcessoHistorico::create([
                'processo_id' => $tramitacao->processo_id,
                'usuario_id'  => Auth::id(),
                'acao'        => 'comentario',
                'detalhes'    => [
                    'tramitacao_id' => $tramitacao->id,
                    'interno'       => $request->boolean('interno', false),
                ],
                'ip'         => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);

            DB::commit();

            return redirect()->back()->with('success', 'Comentário adicionado com sucesso.');
        } catch (\Exception $e) {
            DB::rollBack();

            return redirect()->back()->with('error', 'Erro ao adicionar comentário: ' . $e->getMessage());
        }
    }

    public function anexar(Request $request, $id)
    {
        $request->validate([
            'files'   => ['required', 'array'],
            'files.*' => ['file', 'max:51200'],
        ]);

        try {
            DB::beginTransaction();

            $tramitacao = Tramitacao::findOrFail($id);
            \App\Services\AcaoNoProcesso::exigirAtivoNaTramitacao($tramitacao, Auth::user());
            $anexosNomes = [];

            foreach ($request->file('files') as $file) {
                $path = $file->store(\App\Tenant\TenantStorage::pasta('processos'), 'documentos');

                ProcessoAnexo::create([
                    'processo_id'   => $tramitacao->processo_id,
                    'tramitacao_id' => $tramitacao->id,
                    'nome'          => $file->getClientOriginalName(),
                    'arquivo_path'  => $path,
                    'tamanho'       => $file->getSize(),
                    'mime_type'     => $file->getMimeType(),
                    'hash_sha256'   => hash_file('sha256', $file->getRealPath()),
                    'enviado_por'   => Auth::id(),
                ]);

                $anexosNomes[] = $file->getClientOriginalName();
            }

            ProcessoHistorico::create([
                'processo_id' => $tramitacao->processo_id,
                'usuario_id'  => Auth::id(),
                'acao'        => 'anexo',
                'detalhes'    => [
                    'tramitacao_id' => $tramitacao->id,
                    'arquivos'      => $anexosNomes,
                ],
                'ip'         => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);

            DB::commit();

            return redirect()->back()->with('success', 'Anexo(s) adicionado(s) com sucesso.');
        } catch (\Exception $e) {
            DB::rollBack();

            return redirect()->back()->with('error', 'Erro ao anexar arquivo(s): ' . $e->getMessage());
        }
    }

    /** Despacho do editor com as variáveis trocadas e o HTML limpo (TR 4.16); vazio vira null. */
    private function textoDoDespacho(Request $request, \App\Models\Processo\Processo $processo, \App\Models\Processo\Tramitacao $etapa): ?string
    {
        $html = \App\Support\Variaveis::aplicar($request->input('despacho'), $processo->variaveisDespacho($etapa->setor_destino));

        return trim(strip_tags($html, '<img>')) === '' ? null : $html;
    }
}
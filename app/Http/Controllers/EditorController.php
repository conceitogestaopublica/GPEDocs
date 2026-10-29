<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Support\DocumentoRedigido;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Apoio ao editor (TR 4.16.13–4.16.18): sugestões do @ (pessoas), do ! (quem assina) e do
 * # (documentos), e o PDF que o contato externo mencionado abre pelo link do e-mail.
 */
class EditorController extends Controller
{
    public function sugestoes(Request $request)
    {
        $q = trim((string) $request->input('q'));
        $tipo = $request->input('tipo');
        $escapado = str_replace(['%', '_'], ['\%', '\_'], mb_strtolower($q));
        $termo = "%{$escapado}%";
        $comeco = "{$escapado}%";   // quem começa com o que foi digitado vem primeiro

        if ($tipo === 'documentos') {
            $fontes = [
                ['memorando', 'proc_memorandos', 'numero'], ['circular', 'proc_circulares', 'numero'],
                ['oficio', 'proc_oficios', 'numero'], ['processo', 'proc_processos', 'numero_protocolo'],
            ];
            $itens = [];
            foreach ($fontes as [$t, $tabela, $col]) {
                DB::table($tabela)->where('ug_id', session('ug_id'))->whereNull('deleted_at')
                    // Requerimento do servidor não aparece nas referências de quem não é de GP (TR 4.36.1)
                    ->when($t === 'processo' && ! \App\Support\RequerimentoServidor::veTodos($request->user()), fn ($w) => $w->whereNotIn('tipo_processo_id', \App\Support\RequerimentoServidor::assuntos()))
                    ->when($q !== '', fn ($w) => $w->where(fn ($x) => $x->whereRaw("lower({$col}) like ?", [$termo])->orWhereRaw('lower(assunto) like ?', [$termo])))
                    ->orderByDesc('id')->limit(4)->get(['id', $col . ' as numero', 'assunto'])
                    ->each(function ($r) use (&$itens, $t) {
                        $itens[] = ['id' => "{$t}:{$r->id}", 'label' => $r->numero, 'detalhe' => DocumentoRedigido::rotulo($t) . ' · ' . $r->assunto,
                            'url' => DocumentoRedigido::url($t, (int) $r->id)];
                    });
            }

            return response()->json(array_slice($itens, 0, 12));
        }

        // Pessoas: servidores da UG e, no @ e no !, os contatos externos (assinatura exige CPF e e-mail)
        $assinar = $tipo === 'assinatura';
        $servidores = DB::table('users as u')->leftJoin('ug_organograma as o', 'o.id', '=', 'u.unidade_id')
            ->where('u.tipo', 'interno')->whereNull('u.suspenso_em')->where('u.ug_id', session('ug_id'))
            ->when($q !== '', fn ($w) => $w->whereRaw('lower(u.name) like ?', [$termo]))
            ->orderByRaw('lower(u.name) like ? desc', [$comeco])->orderBy('u.name')->limit(8)->get(['u.id', 'u.name', 'u.cargo', 'o.nome as setor'])
            ->map(fn ($u) => ['id' => "usuario:{$u->id}", 'label' => $u->name, 'detalhe' => collect([$u->cargo, $u->setor])->filter()->implode(' · ') ?: 'Servidor']);
        $contatos = DB::table('contatos')->whereNull('unificado_em_id')->where('tipo', '<>', 'setor_pj')
            ->when($assinar, fn ($w) => $w->whereNotNull('cpf')->whereNotNull('email'), fn ($w) => $w->whereNotNull('email'))
            ->when($q !== '', fn ($w) => $w->whereRaw('lower(nome) like ?', [$termo]))
            ->orderByRaw('lower(nome) like ? desc', [$comeco])->orderBy('nome')->limit(5)->get(['id', 'nome', 'email'])
            ->map(fn ($c) => ['id' => "contato:{$c->id}", 'label' => $c->nome, 'detalhe' => "Contato externo · {$c->email}"]);

        return response()->json($servidores->concat($contatos)->values());
    }

    /**
     * Prévia em PDF do que está na tela, sem gravar nada (TR 4.16.12). Mesmo PDF do envio,
     * com "PRÉVIA" no lugar do número.
     */
    public function previa(Request $request)
    {
        $d = $request->validate([
            'modulo' => ['required', \Illuminate\Validation\Rule::in(array_keys(DocumentoRedigido::TIPOS))],
            'dados'  => ['required', 'string', 'max:20000000'],
        ]);
        $dados = json_decode($d['dados'], true) ?: [];
        $user = $request->user();
        $classe = DocumentoRedigido::TIPOS[$d['modulo']][0];
        $doc = (new $classe())->forceFill(\Illuminate\Support\Arr::only($dados, [
            'assunto', 'conteudo', 'setor_origem', 'confidencial', 'destino_tipo', 'destino_setores',
            'destinatario_nome', 'destinatario_cargo', 'destinatario_orgao', 'destinatario_email',
        ]))->forceFill([
            'numero' => 'PRÉVIA', 'qr_code_token' => 'previa', 'ug_id' => session('ug_id'),
            'remetente_id' => $user->id, 'created_at' => now(),
        ]);
        $doc->setRelation('remetente', $user)->setRelation('destinatarios', collect())
            ->setRelation('respostas', collect())->setRelation('digitador', null);

        return response(DocumentoRedigido::pdfBytes($d['modulo'], $doc), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="previa-' . $d['modulo'] . '.pdf"',
            'Cache-Control' => 'no-store',
        ]);
    }
    /** PDF do documento para o contato mencionado (link assinado do e-mail, 30 dias). */
    public function publico(string $tipo, int $id)
    {
        abort_unless(isset(DocumentoRedigido::TIPOS[$tipo]), 404);
        $doc = DocumentoRedigido::TIPOS[$tipo][0]::withoutGlobalScopes()->with(DocumentoRedigido::TIPOS[$tipo][4])->findOrFail($id);
        abort_unless(DB::table('ged_marcacoes')->where(['origem_tipo' => $tipo, 'origem_id' => $id, 'tipo' => 'mencao', 'alvo_tipo' => 'contato'])->exists(), 404);

        return response(DocumentoRedigido::pdfBytes($tipo, $doc), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="' . str_replace('/', '-', DocumentoRedigido::rotulo($tipo) . ' ' . $doc->numero) . '.pdf"',
        ]);
    }
}

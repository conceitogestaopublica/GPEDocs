/**
 * Peças da tela de criação com rascunho automático (TR 4.16.6–4.16.8): a faixa de estado
 * (salvo, salvando, em edição por outra pessoa) e a lista de anexos guardados no rascunho.
 */
import { useRef } from 'react';
import { useConfirm } from './ConfirmProvider';

const hora = (iso) => (iso ? new Date(iso).toLocaleTimeString('pt-BR', { hour: '2-digit', minute: '2-digit' }) : '');

export function EstadoRascunho({ r }) {
    const confirmar = useConfirm();
    if (r.travadoPor) {
        return (
            <div className="flex flex-wrap items-center gap-3 rounded-lg border border-amber-300 bg-amber-50 px-4 py-3 text-sm text-amber-900">
                <i className="fas fa-lock" />
                <span className="flex-1"><strong>{r.travadoPor}</strong> está editando este rascunho agora. A tela fica só para leitura enquanto essa pessoa estiver nele (a trava solta sozinha até 2 minutos depois que ela sair).</span>
                <button type="button" onClick={() => window.location.reload()} className="text-xs font-semibold underline">Tentar de novo</button>
            </div>
        );
    }
    const texto = {
        novo: 'O rascunho é salvo sozinho enquanto você escreve.',
        pendente: 'Alterações não salvas…',
        salvando: 'Salvando rascunho…',
        salvo: r.salvoEm ? `Rascunho salvo às ${hora(r.salvoEm)} — qualquer pessoa do seu setor pode continuar.` : 'Rascunho salvo.',
        erro: 'Não foi possível salvar o rascunho agora; ele será tentado de novo na próxima alteração.',
    }[r.estado];
    const descartar = async () => {
        if (await confirmar({ titulo: 'Descartar o rascunho?', descricao: 'O texto e os anexos guardados somem. Não dá para desfazer.', tom: 'perigo', rotuloConfirmar: 'Descartar' })) r.descartar();
    };
    return (
        <div className={`flex flex-wrap items-center gap-2 text-xs ${r.estado === 'erro' ? 'text-red-600' : 'text-gray-500'}`}>
            <i className={`fas ${r.estado === 'salvando' || r.estado === 'pendente' ? 'fa-spinner fa-spin' : r.estado === 'erro' ? 'fa-triangle-exclamation' : 'fa-cloud'}`} />
            <span>{texto}</span>
            {r.id && <button type="button" onClick={descartar} className="ml-auto text-red-600 hover:underline"><i className="fas fa-trash-can mr-1" />Descartar rascunho</button>}
        </div>
    );
}

export function AnexosRascunho({ r, rotulo = 'Anexos' }) {
    const input = useRef(null);
    const tamanho = (b) => (b > 1048576 ? `${(b / 1048576).toFixed(1)} MB` : `${Math.max(1, Math.round(b / 1024))} KB`);
    return (
        <div>
            <label className="block text-sm font-medium text-gray-700 mb-1"><i className="fas fa-paperclip text-xs text-gray-400 mr-1" />{rotulo}</label>
            {r.anexos.length > 0 && (
                <ul className="mb-2 divide-y divide-gray-100 rounded-lg border border-gray-200">
                    {r.anexos.map((a) => (
                        <li key={a.id} className="flex items-center gap-2 px-3 py-1.5 text-sm">
                            <i className="fas fa-file text-gray-400" />
                            <span className="flex-1 truncate text-gray-800">{a.nome}</span>
                            <span className="text-xs text-gray-500">{tamanho(a.tamanho)}</span>
                            {! r.travadoPor && (
                                <button type="button" onClick={() => r.removerAnexo(a.id)} className="text-xs text-red-600 hover:underline" aria-label={`Remover ${a.nome}`}>Remover</button>
                            )}
                        </li>
                    ))}
                </ul>
            )}
            <input ref={input} type="file" multiple className="hidden" onChange={(e) => { r.anexar(e.target.files); e.target.value = ''; }} />
            <button type="button" disabled={!! r.travadoPor} onClick={() => input.current?.click()}
                className="px-3 py-1.5 rounded-lg bg-blue-50 text-blue-700 text-sm font-medium hover:bg-blue-100 disabled:opacity-50">
                <i className="fas fa-plus mr-1" />Adicionar arquivos
            </button>
            {r.enviando > 0 && <span className="ml-2 text-xs text-gray-500"><i className="fas fa-spinner fa-spin mr-1" />Enviando {r.enviando} arquivo(s)…</span>}
            <p className="mt-1 text-[11px] text-gray-500">Os arquivos ficam guardados no rascunho e vão junto quando você enviar.</p>
        </div>
    );
}

/** Valores iniciais do formulário: o padrão da tela coberto pelo que estava no rascunho (sem arquivos nem contrassenha). */
// Nulo no rascunho fica com o padrão da tela: o servidor guarda texto vazio como null, e null no FormData vira "null".
export const comRascunho = (rascunho, padrao) => ({
    ...padrao,
    ...Object.fromEntries(Object.entries(rascunho?.dados || {}).map(([k, v]) => [k, v ?? padrao[k] ?? ''])),
    files: [], contrassenha: '',
});

/** O que vai para o rascunho: tudo menos arquivos (sobem à parte) e a contrassenha (segredo). */
export const dadosDoRascunho = ({ files, contrassenha, ...resto }) => resto;

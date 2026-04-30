/**
 * CadastroLayout — container padrao para telas de cadastro (criar/editar).
 *
 * Estrutura:
 *   ┌──────────────────────────┐ ┌───────────────────┐
 *   │ titulo + breadcrumb       │ │ RESUMO            │
 *   │                           │ │ - campo: valor    │
 *   │ ┌─────────────────────┐   │ │ - campo: valor    │
 *   │ │ Cards de dados      │   │ │ ...               │
 *   │ └─────────────────────┘   │ │ aviso obrigatorios│
 *   │                           │ └───────────────────┘
 *   │ ┌─────────────────────┐   │
 *   │ │ footer: cancel/save │   │
 *   │ └─────────────────────┘   │
 *   └──────────────────────────┘
 *
 * Props:
 *   titulo, subtitulo: cabecalho
 *   voltarHref, voltarLabel: link de voltar (opcional)
 *   resumo: array de {label, valor, icone?, vazio?}
 *   obrigatoriosFaltando: bool — se true mostra mensagem amarela
 *   onCancelar, onSalvar
 *   processing: bool
 *   labelSalvar: texto do botao
 *   children: cards do formulario
 */
import { Link } from '@inertiajs/react';
import Button from './Button';

export default function CadastroLayout({
    titulo,
    subtitulo,
    voltarHref,
    voltarLabel,
    resumo = [],
    obrigatoriosFaltando = false,
    onCancelar,
    onSalvar,
    processing = false,
    labelSalvar = 'Salvar',
    iconeSalvar = 'fas fa-check',
    children,
}) {
    return (
        <div className="grid grid-cols-1 lg:grid-cols-[1fr_320px] gap-4">
            {/* Coluna principal */}
            <div className="space-y-4">
                {/* Cabecalho */}
                <div className="flex items-start gap-3">
                    {voltarHref && (
                        <Link href={voltarHref}
                            className="w-9 h-9 rounded-lg border border-gray-200 hover:bg-gray-100 flex items-center justify-center text-gray-500 transition-colors mt-0.5"
                            title={voltarLabel || 'Voltar'}>
                            <i className="fas fa-arrow-left text-xs" />
                        </Link>
                    )}
                    <div className="flex-1">
                        <h1 className="text-lg font-bold text-gray-800">{titulo}</h1>
                        {subtitulo && <p className="text-xs text-gray-500 mt-0.5">{subtitulo}</p>}
                    </div>
                </div>

                {/* Cards do form */}
                <div className="space-y-3">
                    {children}
                </div>

                {/* Footer */}
                <div className="bg-white rounded-2xl border border-gray-200 p-4 flex items-center justify-between">
                    <p className="text-xs text-gray-500">Revise os dados antes de salvar.</p>
                    <div className="flex gap-2">
                        <Button variant="secondary" type="button" onClick={onCancelar}>
                            Cancelar
                        </Button>
                        <Button type="button" onClick={onSalvar} loading={processing} icon={iconeSalvar}
                            disabled={obrigatoriosFaltando}>
                            {labelSalvar}
                        </Button>
                    </div>
                </div>
            </div>

            {/* Resumo lateral */}
            <aside className="space-y-3">
                <div className="bg-white rounded-2xl border border-gray-200 p-4 sticky top-4">
                    <div className="flex items-center gap-2 mb-3 pb-3 border-b border-gray-100">
                        <i className="fas fa-list-check text-blue-500" />
                        <h2 className="text-xs font-bold text-gray-700 uppercase tracking-wide">Resumo</h2>
                    </div>

                    <div className="space-y-2">
                        {resumo.map((item, i) => (
                            <ResumoItem key={i} {...item} />
                        ))}
                    </div>

                    {obrigatoriosFaltando && (
                        <div className="mt-4 bg-amber-50 border border-amber-200 rounded-lg p-3">
                            <p className="text-[11px] text-amber-800 leading-relaxed">
                                <i className="fas fa-exclamation-triangle mr-1" />
                                Preencha os campos obrigatorios para continuar
                            </p>
                        </div>
                    )}
                </div>
            </aside>
        </div>
    );
}

function ResumoItem({ label, valor, icone, vazio }) {
    const preenchido = ! vazio && valor;
    return (
        <div className={`flex items-start gap-2 p-2 rounded-lg ${preenchido ? 'bg-blue-50/50' : 'bg-gray-50'}`}>
            {icone && (
                <i className={`fas ${icone} text-[11px] mt-0.5 ${preenchido ? 'text-blue-500' : 'text-gray-400'}`} />
            )}
            <div className="flex-1 min-w-0">
                <p className="text-[10px] text-gray-500 uppercase tracking-wide leading-tight">{label}</p>
                <p className={`text-xs leading-tight mt-0.5 truncate ${preenchido ? 'text-gray-800 font-medium' : 'text-gray-400 italic'}`}>
                    {preenchido ? valor : '—'}
                </p>
            </div>
        </div>
    );
}

/**
 * Card de seccao para usar dentro do CadastroLayout.
 */
export function CadastroSecao({ icone, titulo, descricao, children }) {
    return (
        <div className="bg-white rounded-2xl border border-gray-200 p-4">
            <div className="flex items-start gap-3 mb-4 pb-3 border-b border-gray-100">
                {icone && (
                    <div className="w-10 h-10 bg-blue-50 rounded-xl flex items-center justify-center shrink-0">
                        <i className={`fas ${icone} text-blue-600 text-sm`} />
                    </div>
                )}
                <div>
                    <h3 className="text-sm font-bold text-gray-800">{titulo}</h3>
                    {descricao && <p className="text-xs text-gray-500 mt-0.5">{descricao}</p>}
                </div>
            </div>
            <div className="space-y-3">
                {children}
            </div>
        </div>
    );
}

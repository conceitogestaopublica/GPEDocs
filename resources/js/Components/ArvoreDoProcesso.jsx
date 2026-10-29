/**
 * Árvore do processo (TR 4.9.16, 4.38.27–4.38.42): pedir a geração (completa, com
 * assinaturas, personalizada, comprimida, fracionada), acompanhar enquanto gera e baixar
 * — a última de cada configuração e o histórico de todas.
 */
import { useEffect, useState } from 'react';
import { router } from '@inertiajs/react';
import Card from './Card';
import Button from './Button';

const SITUACAO = {
    na_fila: ['Na fila', 'bg-gray-100 text-gray-600'],
    gerando: ['Gerando…', 'bg-blue-100 text-blue-700'],
    pronta:  ['Pronta', 'bg-green-100 text-green-700'],
    erro:    ['Erro', 'bg-red-100 text-red-700'],
};
const fmt = (iso) => iso ? new Date(iso).toLocaleString('pt-BR', { day: '2-digit', month: '2-digit', year: 'numeric', hour: '2-digit', minute: '2-digit' }) : '-';
const tamanho = (b) => !b ? '' : b < 1048576 ? `${(b / 1024).toFixed(0)} KB` : `${(b / 1048576).toFixed(1)} MB`;

export default function ArvoreDoProcesso({ processoId, arvores = [], configuracoes = {}, secoes = {} }) {
    const [configuracao, setConfiguracao] = useState('completa');
    const [escolhidas, setEscolhidas] = useState(Object.keys(secoes).filter(s => s !== 'internos'));
    const gerando = arvores.some(a => ['na_fila', 'gerando'].includes(a.status));

    // Enquanto alguma gera, atualiza a lista sozinha (TR 4.38.30)
    useEffect(() => {
        if (! gerando) return undefined;
        const t = setInterval(() => router.reload({ only: ['arvores'], preserveScroll: true }), 4000);
        return () => clearInterval(t);
    }, [gerando]);

    const ultimas = Object.keys(configuracoes)
        .map(c => arvores.find(a => a.configuracao === c && a.status === 'pronta'))
        .filter(Boolean);
    const pedir = () => router.post(`/processos/${processoId}/arvores`,
        { configuracao, secoes: configuracao === 'personalizada' ? escolhidas : undefined }, { preserveScroll: true });

    const Linha = ({ a }) => (
        <div className="py-2 flex items-start justify-between gap-3 flex-wrap">
            <div className="min-w-0">
                <p className="text-sm font-medium text-gray-800">{configuracoes[a.configuracao] || a.configuracao}</p>
                <p className="text-xs text-gray-500">
                    Pedida em {fmt(a.criada_em)} por {a.solicitante}
                    {a.status === 'pronta' && <> · {a.paginas} fl. · {tamanho(a.tamanho)}{a.arquivo_tipo === 'zip' && ` · ${a.partes} parte(s) em ZIP`}</>}
                </p>
                {a.configuracao === 'personalizada' && a.opcoes?.secoes && (
                    <p className="text-xs text-gray-500">Com: {a.opcoes.secoes.map(s => secoes[s] || s).join(', ')}</p>
                )}
                {a.observacao && <p className="text-xs text-amber-700 whitespace-pre-line mt-0.5">{a.observacao}</p>}
                {a.erro && <p className="text-xs text-red-700 mt-0.5">{a.erro}</p>}
            </div>
            <div className="flex items-center gap-2">
                <span className={`text-[11px] font-medium rounded-full px-2 py-0.5 ${SITUACAO[a.status]?.[1]}`}>{SITUACAO[a.status]?.[0]}</span>
                {a.status === 'pronta' && (
                    <a href={`/arvores/${a.id}/baixar`} className="text-sm text-blue-600 hover:underline"><i className="fas fa-download mr-1" />Baixar</a>
                )}
            </div>
        </div>
    );

    return (
        <div className="space-y-4">
            <Card title="Gerar a árvore do processo">
                <p className="text-sm text-gray-600 mb-3">
                    PDF único e paginado (Fl. N/M) com o processo em ordem cronológica: abertura, despachos, manifestações, comentários e
                    anexos logo depois do ato que os juntou. É gerado em segundo plano — você é avisado quando ficar pronto.
                    Cada geração usa as versões atuais dos documentos.
                </p>
                <div className="space-y-2">
                    {Object.entries(configuracoes).map(([v, r]) => (
                        <label key={v} className="flex items-start gap-2 text-sm text-gray-700 cursor-pointer">
                            <input type="radio" name="configuracao" value={v} checked={configuracao === v} onChange={() => setConfiguracao(v)} className="mt-0.5 text-blue-600" />
                            {r}
                        </label>
                    ))}
                </div>
                {configuracao === 'personalizada' && (
                    <div className="mt-3 ml-6 grid grid-cols-1 md:grid-cols-2 gap-1.5">
                        {Object.entries(secoes).map(([v, r]) => (
                            <label key={v} className="flex items-center gap-2 text-sm text-gray-700 cursor-pointer">
                                <input type="checkbox" checked={escolhidas.includes(v)} className="rounded border-gray-300 text-blue-600"
                                    onChange={(e) => setEscolhidas(e.target.checked ? [...escolhidas, v] : escolhidas.filter(x => x !== v))} />
                                {r}
                            </label>
                        ))}
                    </div>
                )}
                <div className="mt-4">
                    <Button variant="primary" icon="fas fa-sitemap" onClick={pedir} disabled={configuracao === 'personalizada' && ! escolhidas.length}>
                        Gerar árvore
                    </Button>
                </div>
            </Card>

            {ultimas.length > 0 && (
                <Card title="Última de cada configuração">
                    <div className="divide-y divide-gray-100">{ultimas.map(a => <Linha key={a.id} a={a} />)}</div>
                </Card>
            )}

            <Card title={`Árvores geradas (${arvores.length})`}>
                {arvores.length === 0
                    ? <p className="text-sm text-gray-400">Nenhuma árvore gerada ainda.</p>
                    : <div className="divide-y divide-gray-100">{arvores.map(a => <Linha key={a.id} a={a} />)}</div>}
            </Card>
        </div>
    );
}

/**
 * Validador publico de assinaturas ICP-Brasil em PDF.
 * Sem auth — qualquer pessoa pode arrastar um PDF e checar a integridade.
 */
import { Head, Link, useForm } from '@inertiajs/react';
import { useState } from 'react';

export default function ValidarAssinatura({ resultado, arquivo_nome }) {
    const { data, setData, post, processing, errors } = useForm({ pdf: null });
    const [arrastando, setArrastando] = useState(false);

    const submit = (e) => {
        e.preventDefault();
        if (!data.pdf) return;
        post('/validar-assinatura', { forceFormData: true });
    };

    const handleDrop = (e) => {
        e.preventDefault();
        setArrastando(false);
        if (e.dataTransfer.files?.length > 0) {
            setData('pdf', e.dataTransfer.files[0]);
        }
    };

    return (
        <div className="min-h-screen bg-gradient-to-b from-gray-50 to-gray-100 flex items-start justify-center p-4 py-8">
            <Head title="Validar Assinatura ICP-Brasil" />

            <div className="w-full max-w-3xl">
                <div className="text-center mb-6">
                    <div className="w-16 h-16 bg-gradient-to-br from-green-600 to-emerald-700 rounded-2xl flex items-center justify-center mx-auto shadow-lg shadow-green-200 mb-3">
                        <i className="fas fa-shield-alt text-white text-xl" />
                    </div>
                    <h1 className="text-xl font-bold text-gray-800">Validador de Assinatura ICP-Brasil</h1>
                    <p className="text-sm text-gray-500">PAdES-BES — Lei 14.063/2020 art. 4, III</p>
                </div>

                {!resultado && (
                    <div className="bg-white rounded-2xl shadow-xl border border-gray-100 p-6">
                        <form onSubmit={submit}>
                            <div
                                onDragOver={(e) => { e.preventDefault(); setArrastando(true); }}
                                onDragLeave={() => setArrastando(false)}
                                onDrop={handleDrop}
                                className={`border-2 border-dashed rounded-xl p-10 text-center transition-colors ${
                                    arrastando ? 'border-green-500 bg-green-50' : 'border-gray-300 bg-gray-50 hover:bg-gray-100'
                                }`}
                            >
                                <i className="fas fa-file-pdf text-4xl text-gray-400 mb-3 block" />
                                <p className="text-sm text-gray-700 mb-2 font-medium">
                                    Arraste um PDF assinado ou clique para selecionar
                                </p>
                                <input
                                    type="file"
                                    accept=".pdf,application/pdf"
                                    id="pdf-input"
                                    className="hidden"
                                    onChange={(e) => setData('pdf', e.target.files?.[0] ?? null)}
                                />
                                <label htmlFor="pdf-input" className="cursor-pointer text-xs text-blue-600 hover:underline">
                                    Selecionar arquivo
                                </label>
                                {data.pdf && (
                                    <p className="mt-3 text-xs text-gray-600">
                                        <i className="fas fa-file mr-1" />
                                        {data.pdf.name} ({(data.pdf.size / 1024).toFixed(1)} KB)
                                    </p>
                                )}
                            </div>
                            {errors.pdf && <p className="mt-2 text-xs text-red-600">{errors.pdf}</p>}

                            <div className="mt-4 flex items-center justify-between">
                                <p className="text-[10px] text-gray-500">
                                    O arquivo nao e armazenado. Toda validacao ocorre em memoria.
                                </p>
                                <button
                                    type="submit"
                                    disabled={!data.pdf || processing}
                                    className="px-5 py-2 bg-green-600 text-white rounded-lg text-sm font-medium shadow hover:bg-green-700 disabled:opacity-50 transition-colors"
                                >
                                    <i className="fas fa-shield-alt mr-2" />
                                    {processing ? 'Validando...' : 'Validar PDF'}
                                </button>
                            </div>
                        </form>
                    </div>
                )}

                {resultado && (
                    <Resultado
                        resultado={resultado}
                        arquivoNome={arquivo_nome}
                        onNovo={() => window.location.href = '/validar-assinatura'}
                    />
                )}

                <p className="text-center text-[10px] text-gray-400 mt-6">
                    GED — Gestao Eletronica de Documentos — Conceito Gestao Publica
                </p>
            </div>
        </div>
    );
}

function Resultado({ resultado, arquivoNome, onNovo }) {
    const semAssinatura = !resultado.tem_assinatura;
    const algumaInvalida = resultado.assinaturas?.some(
        (a) => !a.verificacao || !a.cadeia_valida || !a.cert_valido_no_tempo
    );
    const status = semAssinatura ? 'sem_assinatura' : algumaInvalida ? 'invalida' : 'valida';

    const statusConfig = {
        valida: {
            wrap:  'bg-green-50 border-b border-green-100',
            badge: 'bg-green-100', icon: 'fa-check-circle text-green-600',
            titulo: 'text-green-800', desc: 'text-green-600',
            tituloTxt: 'Assinatura Valida',
            descTxt:   'Todas as assinaturas foram verificadas com sucesso e a cadeia ICP-Brasil esta integra.',
            iconName:  'check-circle',
        },
        invalida: {
            wrap:  'bg-red-50 border-b border-red-100',
            badge: 'bg-red-100', icon: 'fa-times-circle text-red-600',
            titulo: 'text-red-800', desc: 'text-red-600',
            tituloTxt: 'Problemas na Validacao',
            descTxt:   'Algumas verificacoes falharam — confira os detalhes abaixo.',
            iconName:  'times-circle',
        },
        sem_assinatura: {
            wrap:  'bg-gray-50 border-b border-gray-100',
            badge: 'bg-gray-100', icon: 'fa-question-circle text-gray-600',
            titulo: 'text-gray-800', desc: 'text-gray-600',
            tituloTxt: 'PDF Sem Assinatura',
            descTxt:   'O PDF nao contem assinaturas digitais embutidas.',
            iconName:  'question-circle',
        },
    }[status];

    return (
        <div className="bg-white rounded-2xl shadow-xl border border-gray-100 overflow-hidden">
            <div className={`${statusConfig.wrap} px-6 py-4 flex items-center gap-3`}>
                <div className={`w-12 h-12 ${statusConfig.badge} rounded-full flex items-center justify-center`}>
                    <i className={`fas fa-${statusConfig.iconName} ${statusConfig.icon} text-xl`} />
                </div>
                <div className="flex-1">
                    <p className={`text-base font-semibold ${statusConfig.titulo}`}>{statusConfig.tituloTxt}</p>
                    <p className={`text-xs ${statusConfig.desc}`}>{statusConfig.descTxt}</p>
                </div>
                <button onClick={onNovo} className="text-xs text-blue-600 hover:underline">Nova validacao</button>
            </div>

            <div className="px-6 py-4 border-b border-gray-100">
                <div className="grid grid-cols-2 gap-3 text-xs">
                    <div>
                        <p className="text-gray-400 uppercase font-semibold tracking-wide mb-0.5">Arquivo</p>
                        <p className="text-gray-800 truncate">{arquivoNome ?? '-'}</p>
                    </div>
                    <div>
                        <p className="text-gray-400 uppercase font-semibold tracking-wide mb-0.5">Tamanho</p>
                        <p className="text-gray-800">{(resultado.tamanho_bytes / 1024).toFixed(1)} KB</p>
                    </div>
                    <div>
                        <p className="text-gray-400 uppercase font-semibold tracking-wide mb-0.5">Assinaturas</p>
                        <p className="text-gray-800">{resultado.total_assinaturas}</p>
                    </div>
                    <div>
                        <p className="text-gray-400 uppercase font-semibold tracking-wide mb-0.5">Hash SHA-256</p>
                        <p className="text-gray-800 font-mono text-[10px] truncate">{resultado.pdf_sha256}</p>
                    </div>
                </div>
            </div>

            {resultado.assinaturas?.map((a) => (
                <Assinatura key={a.ordem} a={a} />
            ))}

            <div className="px-6 py-3 bg-gray-50 border-t border-gray-100">
                <p className="text-[10px] text-gray-500 leading-relaxed">
                    <i className="fas fa-info-circle mr-1" />
                    Validacao realizada offline contra a truststore ICP-Brasil instalada no servidor.
                    Nao inclui consulta OCSP/CRL para revogacao em tempo real.
                </p>
            </div>
        </div>
    );
}

function Assinatura({ a }) {
    const tudoOk = a.verificacao && a.cadeia_valida && a.cert_valido_no_tempo;
    const badgeClass = tudoOk ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700';

    return (
        <div className="px-6 py-5 border-b border-gray-100 last:border-b-0">
            <div className="flex items-center gap-2 mb-3">
                <span className={`text-xs px-2 py-0.5 rounded-full ${badgeClass} font-bold`}>
                    Assinatura #{a.ordem}
                </span>
                {a.signatario?.icp_brasil && (
                    <span className="text-[9px] px-1.5 py-0.5 rounded bg-blue-100 text-blue-700 font-bold">ICP-Brasil</span>
                )}
                {a.algoritmo && (
                    <span className="text-[10px] text-gray-500">{a.algoritmo}</span>
                )}
            </div>

            <div className="grid grid-cols-1 md:grid-cols-3 gap-2 text-xs mb-3">
                <CheckBox ok={a.verificacao} label="Integridade criptografica" />
                <CheckBox ok={a.cadeia_valida} label="Cadeia ICP-Brasil" />
                <CheckBox ok={a.cert_valido_no_tempo} label="Cert valido no momento" />
            </div>

            {a.signatario && (
                <div className="bg-gray-50 rounded-lg p-3 space-y-1.5 text-xs">
                    <Linha label="Signatario" valor={a.signatario.cn} />
                    {a.signatario.cpf && <Linha label="CPF" valor={a.signatario.cpf} />}
                    {a.signatario.cnpj && <Linha label="CNPJ" valor={a.signatario.cnpj} />}
                    <Linha label="AC Emissora" valor={a.signatario.issuer_cn} />
                    <Linha label="Numero de serie" valor={a.signatario.serial} mono />
                    <Linha label="Validade do cert" valor={`${a.signatario.valido_de} ate ${a.signatario.valido_ate}`} />
                    <Linha label="Thumbprint SHA-256" valor={a.signatario.thumbprint_sha256} mono />
                </div>
            )}

            {(a.extras?.reason || a.extras?.location || a.extras?.modificado) && (
                <div className="mt-3 grid grid-cols-2 gap-2 text-xs">
                    {a.extras.reason && <Linha label="Razao" valor={a.extras.reason} />}
                    {a.extras.location && <Linha label="Local" valor={a.extras.location} />}
                    {a.extras.modificado && <Linha label="Carimbo de tempo" valor={a.extras.modificado} />}
                    {a.extras.sub_filter && <Linha label="SubFilter" valor={a.extras.sub_filter} mono />}
                </div>
            )}

            {a.erros?.length > 0 && (
                <div className="mt-3 bg-red-50 border border-red-200 rounded-lg p-3">
                    <p className="text-[10px] font-semibold text-red-800 uppercase tracking-wide mb-1">Erros</p>
                    {a.erros.map((e, i) => (
                        <p key={i} className="text-[10px] text-red-700 font-mono">{e}</p>
                    ))}
                </div>
            )}
        </div>
    );
}

function CheckBox({ ok, label }) {
    const wrap = ok ? 'bg-green-50' : 'bg-red-50';
    const icon = ok ? 'fa-check-circle text-green-600' : 'fa-times-circle text-red-600';
    const text = ok ? 'text-green-800' : 'text-red-800';
    return (
        <div className={`flex items-center gap-2 px-2 py-1.5 rounded-lg ${wrap}`}>
            <i className={`fas ${icon}`} />
            <span className={`text-[11px] ${text}`}>{label}</span>
        </div>
    );
}

function Linha({ label, valor, mono }) {
    return (
        <div className="flex items-start gap-2">
            <span className="text-gray-500 text-[10px] uppercase tracking-wide min-w-[100px]">{label}:</span>
            <span className={`text-gray-800 break-all flex-1 ${mono ? 'font-mono text-[10px]' : ''}`}>{valor || '-'}</span>
        </div>
    );
}

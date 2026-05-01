/**
 * Modal de assinatura — reutilizavel entre /assinaturas e /processos/{id}.
 *
 * Aceita 3 modos:
 *   - simples: Lei 14.063/2020 art. 4 I (CPF + IP + geo)
 *   - qualificada: Art. 4 III via certificado A1 (.pfx) ICP-Brasil
 *   - qualificadaA3: Art. 4 III via token/smartcard usando Lacuna Web PKI
 *
 * Props:
 *   assinatura: { id, documento: { nome }, solicitacao: { mensagem } }
 *   onClose: () => void
 */
import { router, useForm } from '@inertiajs/react';
import { useEffect, useState } from 'react';
import Modal from './Modal';
import Button from './Button';

export default function AssinarModal({ assinatura, onClose }) {
    const [tipo, setTipo] = useState('simples');

    if (! assinatura) return null;

    const opcoes = [
        { key: 'simples',     icon: 'pen-nib',     cor: 'blue',   titulo: 'Simples',
          desc: 'Lei 14.063/2020 art. 4, I — CPF + IP + geolocalizacao' },
        { key: 'qualificada', icon: 'shield-alt',  cor: 'green',  titulo: 'Qualificada A1',
          desc: 'Art. 4, III — certificado A1 (.pfx) ICP-Brasil', badge: 'ICP-Brasil' },
        { key: 'qualificadaA3', icon: 'usb',       cor: 'purple', titulo: 'Qualificada A3',
          desc: 'Art. 4, III — token/smartcard via Web PKI', badge: 'Token' },
    ];

    const cores = {
        blue:   { sel: 'border-blue-500 bg-blue-50',     icon: 'text-blue-600',   badge: 'bg-blue-600' },
        green:  { sel: 'border-green-500 bg-green-50',   icon: 'text-green-600',  badge: 'bg-green-600' },
        purple: { sel: 'border-purple-500 bg-purple-50', icon: 'text-purple-600', badge: 'bg-purple-600' },
    };

    return (
        <Modal show={!! assinatura} onClose={onClose} title="Assinar Documento">
            <div className="bg-blue-50 rounded-xl p-4 mb-4">
                <p className="text-sm font-medium text-blue-800">{assinatura.documento?.nome}</p>
                {assinatura.solicitacao?.mensagem && (
                    <p className="text-xs text-blue-600 mt-1">{assinatura.solicitacao.mensagem}</p>
                )}
            </div>

            <div className="grid grid-cols-3 gap-2 mb-4">
                {opcoes.map(op => {
                    const c = cores[op.cor];
                    const ativo = tipo === op.key;
                    return (
                        <button key={op.key} type="button" onClick={() => setTipo(op.key)}
                            className={`text-left p-3 rounded-xl border-2 transition-colors ${
                                ativo ? c.sel : 'border-gray-200 hover:bg-gray-50'
                            }`}>
                            <div className="flex items-center gap-2 mb-1">
                                <i className={`fas fa-${op.icon} ${c.icon} text-sm`} />
                                <span className="text-sm font-semibold text-gray-800">{op.titulo}</span>
                            </div>
                            {op.badge && (
                                <span className={`text-[9px] px-1.5 py-0.5 rounded ${c.badge} text-white font-bold inline-block mb-1`}>
                                    {op.badge}
                                </span>
                            )}
                            <p className="text-[10px] text-gray-500 leading-tight">{op.desc}</p>
                        </button>
                    );
                })}
            </div>

            {tipo === 'simples'        && <FormSimples assinatura={assinatura} onClose={onClose} />}
            {tipo === 'qualificada'    && <FormIcp     assinatura={assinatura} onClose={onClose} />}
            {tipo === 'qualificadaA3'  && <FormIcpA3   assinatura={assinatura} onClose={onClose} />}
        </Modal>
    );
}

function FormSimples({ assinatura, onClose }) {
    const { data, setData, post, processing, errors } = useForm({
        cpf: '',
        geolocalizacao: '',
    });
    const [concordo, setConcordo] = useState(false);

    const requestGeo = () => {
        if (navigator.geolocation) {
            navigator.geolocation.getCurrentPosition(
                (pos) => setData('geolocalizacao', `${pos.coords.latitude},${pos.coords.longitude}`),
                () => {}
            );
        }
    };

    const submit = (e) => {
        e.preventDefault();
        if (! concordo) return;
        requestGeo();
        post(`/assinaturas/${assinatura.id}/assinar`, { onSuccess: onClose });
    };

    return (
        <form onSubmit={submit} className="space-y-4">
            <div>
                <label className="block text-sm font-medium text-gray-700 mb-1">CPF</label>
                <input type="text" value={data.cpf} onChange={(e) => setData('cpf', e.target.value)}
                    className="ds-input" placeholder="000.000.000-00" maxLength={14} />
                {errors.cpf && <p className="mt-1 text-xs text-red-600">{errors.cpf}</p>}
            </div>

            <div className="bg-gray-50 rounded-xl p-3">
                <p className="text-[10px] text-gray-500">
                    <i className="fas fa-info-circle mr-1" />
                    Serao coletados: e-mail, CPF, IP, geolocalizacao (se permitida), data/hora e hash do documento — Lei 14.063/2020 art. 4, I.
                </p>
            </div>

            <label className="flex items-start gap-2 cursor-pointer">
                <input type="checkbox" checked={concordo} onChange={(e) => setConcordo(e.target.checked)}
                    className="rounded border-gray-300 text-blue-600 mt-0.5" />
                <span className="text-sm text-gray-700">Declaro que li e concordo com o conteudo deste documento</span>
            </label>

            <div className="flex justify-end gap-2">
                <Button variant="secondary" type="button" onClick={onClose}>Cancelar</Button>
                <Button type="submit" loading={processing} icon="fas fa-pen-nib" disabled={! concordo}>Assinar</Button>
            </div>
        </form>
    );
}

function FormIcp({ assinatura, onClose }) {
    const { data, setData, post, processing, errors } = useForm({
        pfx: null,
        senha: '',
        razao: 'Assinatura Eletronica Qualificada (Lei 14.063/2020 art. 4, III)',
        local: 'Brasil',
        geolocalizacao: '',
    });
    const [concordo, setConcordo] = useState(false);

    const requestGeo = () => {
        if (navigator.geolocation) {
            navigator.geolocation.getCurrentPosition(
                (pos) => setData('geolocalizacao', `${pos.coords.latitude},${pos.coords.longitude}`),
                () => {}
            );
        }
    };

    const submit = (e) => {
        e.preventDefault();
        if (! concordo || ! data.pfx || ! data.senha) return;
        requestGeo();
        post(`/assinaturas/${assinatura.id}/assinar-icp`, {
            forceFormData: true,
            onSuccess: onClose,
        });
    };

    return (
        <form onSubmit={submit} className="space-y-4">
            <div className="bg-green-50 border border-green-200 rounded-xl p-3">
                <p className="text-[11px] text-green-800 leading-tight">
                    <i className="fas fa-shield-alt mr-1" />
                    <strong>Assinatura Qualificada ICP-Brasil</strong> — equivale juridicamente a assinatura manuscrita.
                    Sera gerado um PDF assinado em PAdES-BES, validavel em qualquer leitor compativel.
                </p>
            </div>

            <div>
                <label className="block text-sm font-medium text-gray-700 mb-1">Certificado digital (.pfx ou .p12)</label>
                <input type="file" accept=".pfx,.p12"
                    onChange={(e) => setData('pfx', e.target.files?.[0] ?? null)}
                    className="block w-full text-sm text-gray-600 file:mr-3 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-xs file:font-medium file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100" />
                {errors.pfx && <p className="mt-1 text-xs text-red-600">{errors.pfx}</p>}
                <p className="mt-1 text-[10px] text-gray-500">Apenas certificados A1. Para A3 (token/smartcard), use a aba A3.</p>
            </div>

            <div>
                <label className="block text-sm font-medium text-gray-700 mb-1">Senha do certificado</label>
                <input type="password" value={data.senha} onChange={(e) => setData('senha', e.target.value)}
                    autoComplete="off" className="ds-input" placeholder="Senha do .pfx" />
                {errors.senha && <p className="mt-1 text-xs text-red-600">{errors.senha}</p>}
                <p className="mt-1 text-[10px] text-gray-500">A senha e usada apenas em memoria para decriptar o PFX e nunca e armazenada.</p>
            </div>

            <details className="bg-gray-50 rounded-xl p-3">
                <summary className="text-xs font-medium text-gray-700 cursor-pointer">Opcoes avancadas</summary>
                <div className="mt-3 space-y-2">
                    <div>
                        <label className="block text-[11px] text-gray-600 mb-1">Razao</label>
                        <input type="text" value={data.razao} onChange={(e) => setData('razao', e.target.value)}
                            className="ds-input text-xs" maxLength={200} />
                    </div>
                    <div>
                        <label className="block text-[11px] text-gray-600 mb-1">Local</label>
                        <input type="text" value={data.local} onChange={(e) => setData('local', e.target.value)}
                            className="ds-input text-xs" maxLength={200} />
                    </div>
                </div>
            </details>

            <label className="flex items-start gap-2 cursor-pointer">
                <input type="checkbox" checked={concordo} onChange={(e) => setConcordo(e.target.checked)}
                    className="rounded border-gray-300 text-green-600 mt-0.5" />
                <span className="text-sm text-gray-700">
                    Declaro que li, concordo com o conteudo do documento e que sou o titular do certificado digital fornecido
                </span>
            </label>

            <div className="flex justify-end gap-2">
                <Button variant="secondary" type="button" onClick={onClose}>Cancelar</Button>
                <Button type="submit" loading={processing} icon="fas fa-shield-alt"
                    disabled={! concordo || ! data.pfx || ! data.senha}>
                    Assinar com ICP-Brasil
                </Button>
            </div>
        </form>
    );
}

function FormIcpA3({ assinatura, onClose }) {
    const [estado, setEstado] = useState('inicializando');
    const [certs, setCerts] = useState([]);
    const [thumbprint, setThumbprint] = useState('');
    const [erro, setErro] = useState(null);
    const [pkiRef, setPkiRef] = useState(null);
    const [progresso, setProgresso] = useState('');
    const [razao, setRazao] = useState('Assinatura Eletronica Qualificada (Lei 14.063/2020 art. 4, III)');
    const [local, setLocal] = useState('Brasil');
    const [concordo, setConcordo] = useState(false);

    useEffect(() => {
        let cancelado = false;
        (async () => {
            try {
                const { LacunaWebPKI } = await import('web-pki');
                const pki = new LacunaWebPKI();
                pki.init({
                    ready: async () => {
                        if (cancelado) return;
                        setPkiRef(pki);
                        setEstado('listando');
                        try {
                            const lista = await pki.listCertificates();
                            if (cancelado) return;
                            setCerts(lista);
                            setEstado('pronto');
                            if (lista.length > 0) setThumbprint(lista[0].thumbprint);
                        } catch (e) {
                            setErro('Falha ao listar certificados: ' + (e?.message || e));
                            setEstado('erro');
                        }
                    },
                    notInstalled: () => { if (! cancelado) setEstado('sem_extensao'); },
                    defaultFail: (e) => {
                        if (cancelado) return;
                        setErro('Web PKI: ' + (e?.message || JSON.stringify(e)));
                        setEstado('erro');
                    },
                });
            } catch (e) {
                setErro('Falha ao carregar Web PKI: ' + (e?.message || e));
                setEstado('erro');
            }
        })();
        return () => { cancelado = true; };
    }, []);

    const obterGeo = () => new Promise((resolve) => {
        if (! navigator.geolocation) return resolve('');
        navigator.geolocation.getCurrentPosition(
            (pos) => resolve(`${pos.coords.latitude},${pos.coords.longitude}`),
            () => resolve(''),
            { timeout: 5000 },
        );
    });

    const csrf = () => document.querySelector('meta[name="csrf-token"]')?.content || '';

    const submit = async (e) => {
        e.preventDefault();
        if (! thumbprint || ! concordo) return;
        setEstado('assinando');
        setErro(null);

        try {
            setProgresso('Lendo certificado do token...');
            const certPem = await pkiRef.readCertificate({ thumbprint });

            setProgresso('Preparando assinatura no servidor...');
            const respPrep = await fetch(`/assinaturas/${assinatura.id}/preparar-icp-a3`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf(), 'Accept': 'application/json' },
                body: JSON.stringify({ cert_pem: certPem, razao, local }),
            });
            const dadosPrep = await respPrep.json();
            if (! respPrep.ok) throw new Error(dadosPrep?.erro || 'Falha ao preparar assinatura');

            setProgresso('Aguardando PIN do token...');
            const assinaturaB64 = await pkiRef.signHash({
                thumbprint, hash: dadosPrep.hash_a_assinar, digestAlgorithm: dadosPrep.algoritmo_digest,
            });

            setProgresso('Finalizando assinatura no servidor...');
            const geo = await obterGeo();
            const respFin = await fetch(`/assinaturas/${assinatura.id}/finalizar-icp-a3`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf(), 'Accept': 'application/json' },
                body: JSON.stringify({ sessao_id: dadosPrep.sessao_id, assinatura_b64: assinaturaB64, geolocalizacao: geo }),
            });
            const dadosFin = await respFin.json();
            if (! respFin.ok) throw new Error(dadosFin?.erro || 'Falha ao finalizar assinatura');

            setEstado('sucesso');
            setProgresso('Documento assinado com sucesso.');
            setTimeout(() => { onClose(); router.reload(); }, 1200);
        } catch (e) {
            setErro(e?.message || String(e));
            setEstado('erro');
            setProgresso('');
        }
    };

    if (estado === 'sem_extensao') {
        return (
            <div className="space-y-4">
                <div className="bg-amber-50 border border-amber-200 rounded-xl p-4">
                    <p className="text-sm font-semibold text-amber-800 mb-1">
                        <i className="fas fa-puzzle-piece mr-1" />
                        Extensao Web PKI nao detectada
                    </p>
                    <p className="text-xs text-amber-700 mb-3">
                        Para assinar com token/smartcard, voce precisa instalar a extensao gratuita Web PKI da Lacuna.
                    </p>
                    <a href="https://get.webpkiplugin.com" target="_blank" rel="noopener"
                        className="inline-flex items-center gap-2 px-3 py-1.5 bg-amber-600 text-white rounded-lg text-xs font-medium hover:bg-amber-700">
                        <i className="fas fa-download" />Baixar Web PKI
                    </a>
                </div>
                <div className="flex justify-end">
                    <Button variant="secondary" type="button" onClick={onClose}>Fechar</Button>
                </div>
            </div>
        );
    }

    return (
        <form onSubmit={submit} className="space-y-4">
            <div className="bg-purple-50 border border-purple-200 rounded-xl p-3">
                <p className="text-[11px] text-purple-800 leading-tight">
                    <i className="fas fa-usb mr-1" />
                    <strong>Assinatura A3 (token/smartcard)</strong> — sua chave privada permanece no hardware. O token solicitara o PIN no momento da assinatura.
                </p>
            </div>

            {(estado === 'inicializando' || estado === 'listando') && (
                <div className="text-center py-4 text-xs text-gray-500">
                    <i className="fas fa-spinner fa-spin mr-2" />
                    {estado === 'inicializando' ? 'Conectando com Web PKI...' : 'Listando certificados disponiveis...'}
                </div>
            )}

            {(estado === 'pronto' || estado === 'assinando' || estado === 'sucesso') && (
                <>
                    <div>
                        <label className="block text-sm font-medium text-gray-700 mb-1">Certificado ({certs.length})</label>
                        {certs.length === 0 ? (
                            <p className="text-xs text-red-600">Nenhum certificado encontrado. Verifique se o token esta conectado.</p>
                        ) : (
                            <select value={thumbprint} onChange={(e) => setThumbprint(e.target.value)}
                                className="ds-input" disabled={estado === 'assinando'}>
                                {certs.map(c => (
                                    <option key={c.thumbprint} value={c.thumbprint}>
                                        {c.subjectName || c.commonName || c.thumbprint.slice(0,8)}
                                        {c.issuerName ? ` — ${c.issuerName}` : ''}
                                        {c.validityEnd ? ` (valido ate ${new Date(c.validityEnd).toLocaleDateString('pt-BR')})` : ''}
                                    </option>
                                ))}
                            </select>
                        )}
                    </div>

                    <details className="bg-gray-50 rounded-xl p-3">
                        <summary className="text-xs font-medium text-gray-700 cursor-pointer">Opcoes avancadas</summary>
                        <div className="mt-3 space-y-2">
                            <div>
                                <label className="block text-[11px] text-gray-600 mb-1">Razao</label>
                                <input type="text" value={razao} onChange={(e) => setRazao(e.target.value)}
                                    className="ds-input text-xs" maxLength={200} disabled={estado === 'assinando'} />
                            </div>
                            <div>
                                <label className="block text-[11px] text-gray-600 mb-1">Local</label>
                                <input type="text" value={local} onChange={(e) => setLocal(e.target.value)}
                                    className="ds-input text-xs" maxLength={200} disabled={estado === 'assinando'} />
                            </div>
                        </div>
                    </details>

                    <label className="flex items-start gap-2 cursor-pointer">
                        <input type="checkbox" checked={concordo} onChange={(e) => setConcordo(e.target.checked)}
                            className="rounded border-gray-300 text-purple-600 mt-0.5" disabled={estado === 'assinando'} />
                        <span className="text-sm text-gray-700">
                            Declaro que li e concordo com o conteudo deste documento e que sou o titular do certificado selecionado
                        </span>
                    </label>

                    {progresso && (
                        <div className="bg-blue-50 border border-blue-200 rounded-lg p-3 flex items-center gap-2">
                            {estado === 'assinando' && <i className="fas fa-spinner fa-spin text-blue-600" />}
                            {estado === 'sucesso' && <i className="fas fa-check-circle text-green-600" />}
                            <span className="text-xs text-blue-800">{progresso}</span>
                        </div>
                    )}

                    <div className="flex justify-end gap-2">
                        <Button variant="secondary" type="button" onClick={onClose} disabled={estado === 'assinando'}>Cancelar</Button>
                        <Button type="submit" loading={estado === 'assinando'} icon="fas fa-usb"
                            disabled={! thumbprint || ! concordo || estado === 'assinando' || estado === 'sucesso'}>
                            Assinar com token A3
                        </Button>
                    </div>
                </>
            )}

            {erro && (
                <div className="bg-red-50 border border-red-200 rounded-lg p-3">
                    <p className="text-xs text-red-800 font-medium mb-1">
                        <i className="fas fa-exclamation-triangle mr-1" />Erro
                    </p>
                    <p className="text-[11px] text-red-700 break-all">{erro}</p>
                </div>
            )}
        </form>
    );
}

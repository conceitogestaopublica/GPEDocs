/**
 * Entrada com certificado digital ICP-Brasil (e-CPF A1 instalado no
 * computador ou A3 em token/cartão), pela extensão Web PKI: o servidor manda
 * um desafio, o certificado assina e o servidor confere cadeia, revogação e
 * o CPF do titular. A chave privada não sai da máquina.
 */
import { useEffect, useState } from 'react';

export default function EntrarComCertificado({ lembrar = false, onVoltar }) {
    const [estado, setEstado] = useState('iniciando'); // iniciando | sem_extensao | pronto | entrando | erro
    const [pki, setPki] = useState(null);
    const [certs, setCerts] = useState([]);
    const [thumbprint, setThumbprint] = useState('');
    const [erro, setErro] = useState(null);

    useEffect(() => {
        let cancelado = false;
        // Sem a extensão, o aviso de "não instalada" pode demorar: não deixa a tela parada
        const limite = setTimeout(() => !cancelado && setEstado(e => (e === 'iniciando' ? 'sem_extensao' : e)), 6000);
        (async () => {
            try {
                const { LacunaWebPKI } = await import('web-pki');
                const p = new LacunaWebPKI();
                p.init({
                    ready: async () => {
                        if (cancelado) return;
                        setPki(p);
                        try {
                            // Só certificados ICP-Brasil com CPF (e-CPF) e dentro da validade
                            const lista = (await p.listCertificates()).filter(c => c.pkiBrazil?.cpf
                                && (!c.validityEnd || new Date(c.validityEnd) > new Date()));
                            if (cancelado) return;
                            setCerts(lista);
                            if (lista.length) setThumbprint(lista[0].thumbprint);
                            setEstado('pronto');
                        } catch (e) {
                            setErro('Não foi possível listar os certificados: ' + (e?.message || e));
                            setEstado('erro');
                        }
                    },
                    notInstalled: () => !cancelado && setEstado('sem_extensao'),
                    defaultFail: (e) => {
                        if (cancelado) return;
                        setErro(e?.message || 'Falha na extensão Web PKI.');
                        setEstado('erro');
                    },
                });
            } catch (e) {
                setErro('Falha ao carregar a extensão Web PKI.');
                setEstado('erro');
            }
        })();
        return () => { cancelado = true; clearTimeout(limite); };
    }, []);

    const entrar = async () => {
        setEstado('entrando');
        setErro(null);
        const xsrf = decodeURIComponent((document.cookie.match(/XSRF-TOKEN=([^;]+)/) || [])[1] || '');
        const cabecalhos = { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-XSRF-TOKEN': xsrf };
        try {
            const certPem = await pki.readCertificate({ thumbprint });
            const desafio = await (await fetch('/login/certificado/desafio', { headers: cabecalhos })).json();
            const assinatura = await pki.signHash({ thumbprint, hash: desafio.hash, digestAlgorithm: desafio.algoritmo });
            const resp = await fetch('/login/certificado', {
                method: 'POST', headers: cabecalhos,
                body: JSON.stringify({ cert_pem: certPem, assinatura_b64: assinatura, lembrar }),
            });
            const dados = await resp.json();
            if (!resp.ok) throw new Error(dados?.erro || 'Não foi possível entrar com o certificado.');
            window.location.href = dados.destino;
        } catch (e) {
            setErro(e?.message || String(e));
            setEstado('pronto');
        }
    };

    return (
        <div className="space-y-4">
            {estado === 'iniciando' && (
                <p className="text-sm text-slate-500 text-center py-4"><i className="fas fa-spinner fa-spin mr-2" />Procurando certificados…</p>
            )}

            {estado === 'sem_extensao' && (
                <div className="bg-amber-50 border border-amber-200 rounded-xl p-4 text-sm">
                    <p className="font-semibold text-amber-800"><i className="fas fa-puzzle-piece mr-1" />Falta a extensão Web PKI</p>
                    <p className="text-xs text-amber-700 mt-1 mb-3">
                        É ela que permite ao navegador usar o certificado instalado no computador ou no token. É gratuita.
                    </p>
                    <a href="https://get.webpkiplugin.com" target="_blank" rel="noopener noreferrer"
                        className="inline-flex items-center gap-2 px-3 py-1.5 bg-amber-600 text-white rounded-lg text-xs font-medium hover:bg-amber-700">
                        <i className="fas fa-download" />Instalar Web PKI
                    </a>
                </div>
            )}

            {(estado === 'pronto' || estado === 'entrando') && (
                certs.length === 0 ? (
                    <p className="text-sm text-red-600 bg-red-50 border border-red-200 rounded-xl p-3">
                        Nenhum e-CPF válido encontrado. Confira se o token está conectado ou se o certificado A1 está instalado.
                    </p>
                ) : (
                    <>
                        <div>
                            <label className="block text-sm font-medium text-slate-600 mb-1.5">Certificado</label>
                            <select value={thumbprint} onChange={(e) => setThumbprint(e.target.value)} disabled={estado === 'entrando'}
                                className="w-full px-4 py-3 rounded-xl text-sm border border-slate-200 bg-slate-50/60 focus:bg-white focus:border-blue-500 outline-none">
                                {certs.map(c => (
                                    <option key={c.thumbprint} value={c.thumbprint}>
                                        {c.subjectName}{c.issuerName ? ` — ${c.issuerName}` : ''}
                                    </option>
                                ))}
                            </select>
                        </div>
                        <button type="button" onClick={entrar} disabled={estado === 'entrando' || !thumbprint}
                            className="w-full py-3 rounded-xl text-white text-sm font-semibold shadow-lg shadow-blue-500/30 disabled:opacity-60
                                bg-gradient-to-r from-cyan-600 to-blue-700 hover:from-cyan-700 hover:to-blue-800 flex items-center justify-center gap-2">
                            {estado === 'entrando'
                                ? <><i className="fas fa-spinner fa-spin" /> Conferindo certificado…</>
                                : <><i className="fas fa-id-card" /> Entrar com este certificado</>}
                        </button>
                    </>
                )
            )}

            {erro && <p className="text-sm text-red-600 flex items-start gap-1.5"><i className="fas fa-exclamation-circle text-xs mt-1" />{erro}</p>}

            <button type="button" onClick={onVoltar} className="w-full text-sm text-slate-500 hover:text-slate-700">
                <i className="fas fa-arrow-left mr-1" />Entrar com CPF/e-mail e senha
            </button>
        </div>
    );
}

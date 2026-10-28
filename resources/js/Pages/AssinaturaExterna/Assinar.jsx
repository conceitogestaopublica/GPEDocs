/**
 * Assinatura por link — signatário externo (cidadão, empresa, fornecedor).
 *
 * Uma página só, com o estado decidido no servidor (AssinaturaExternaController::show):
 * acesso (entrar/cadastrar) → confirmar e-mail → assinar (PDF + carimbo) → assinado.
 */
import { Head, router, useForm, usePage } from '@inertiajs/react';
import { useCallback, useEffect, useRef, useState } from 'react';
import Captcha from '../../Components/Captcha';
import * as pdfjs from 'pdfjs-dist';
// ?worker (e não ?url): o Vite empacota o worker como .js — o nginx serve .mjs como
// application/octet-stream e o navegador recusa carregar o worker.
import PdfWorker from 'pdfjs-dist/build/pdf.worker.min.mjs?worker';

let workerPdf = null;
function garantirWorker() {
    if (! workerPdf) {
        workerPdf = new PdfWorker();
        pdfjs.GlobalWorkerOptions.workerPort = workerPdf;
    }
}

const ORIENTACOES = [
    { rotacao: 0, label: 'Padrão' },
    { rotacao: 180, label: 'Invertido' },
    { rotacao: 270, label: 'Esquerda' },
    { rotacao: 90, label: 'Direita' },
];

export default function Assinar(props) {
    const { estado, convite, token } = props;
    const { flash } = usePage().props;
    // Link de pacote (vários documentos) ou de um documento: muda só o endereço das ações.
    const base = props.pacote ? `/assinar/pacote/${token}` : `/assinar/${token}`;

    return (
        <div className="min-h-screen bg-[#F2F5F8]">
            <Head title="Assinatura de documento" />

            <header className="bg-[#083344] text-white">
                <div className="max-w-6xl mx-auto px-4 py-3 flex items-center justify-between gap-3">
                    <div className="flex items-center gap-3 min-w-0">
                        <div className="w-9 h-9 rounded-lg bg-primary-500 flex items-center justify-center shrink-0">
                            <i className="fas fa-file-signature" />
                        </div>
                        <div className="min-w-0">
                            <p className="text-[11px] uppercase tracking-wider text-cyan-200 truncate">{convite?.orgao || 'GPE Docs'}</p>
                            <p className="text-sm font-semibold">Assinatura eletrônica de documento</p>
                        </div>
                    </div>
                    {['assinar', 'confirmar_email', 'aguardando_vez', 'outra_conta', 'assinado', 'pacote'].includes(estado) && (
                        <button onClick={() => router.post(`${base}/sair`)}
                            className="text-xs px-3 py-1.5 rounded-lg border border-cyan-700 hover:bg-cyan-900 transition-colors">
                            <i className="fas fa-sign-out-alt mr-1" />Sair
                        </button>
                    )}
                </div>
            </header>

            <main className="max-w-6xl mx-auto px-4 py-6 space-y-4">
                {flash?.success && <Aviso tom="sucesso">{flash.success}</Aviso>}
                {flash?.error && <Aviso tom="erro">{flash.error}</Aviso>}

                {convite && estado !== 'assinar' && <CartaoConvite convite={convite} pacote={props.pacote} />}

                {estado === 'invalido' && (
                    <Mensagem icone="fa-link-slash" tom="erro" titulo="Link inválido"
                        texto="Este link de assinatura não existe. Confira se o endereço foi copiado inteiro ou peça um novo link a quem enviou o documento." />
                )}
                {estado === 'expirado' && (
                    <Mensagem icone="fa-hourglass-end" tom="aviso" titulo="Link vencido"
                        texto={`O prazo deste link terminou${convite?.expira_em ? ` em ${convite.expira_em}` : ''}. Peça um novo envio a quem mandou o documento.`} />
                )}
                {estado === 'encerrado' && (
                    <Mensagem icone="fa-ban" tom="aviso" titulo="Solicitação encerrada"
                        texto="Esta solicitação de assinatura foi cancelada ou recusada e não aceita mais assinaturas." />
                )}
                {estado === 'recusado' && (
                    <Mensagem icone="fa-times-circle" tom="aviso" titulo="Assinatura recusada"
                        texto="Você recusou assinar este documento. Quem enviou já foi avisado." />
                )}
                {estado === 'erro_pdf' && (
                    <Mensagem icone="fa-file-circle-exclamation" tom="erro" titulo="Não foi possível abrir o documento"
                        texto="O arquivo deste documento não pôde ser lido. Avise quem enviou o link." />
                )}
                {estado === 'aguardando_vez' && (
                    <Mensagem icone="fa-clock" tom="info" titulo="Aguardando outras assinaturas"
                        texto="Este documento é assinado em ordem. Você poderá assinar quando os signatários anteriores concluírem — o link continua o mesmo." />
                )}
                {estado === 'outra_conta' && (
                    <Mensagem icone="fa-user-lock" tom="aviso" titulo="Você entrou com outra conta"
                        texto={`Você está conectado como ${props.logado_como}, mas este documento foi enviado para ${convite?.nome} (CPF ${convite?.cpf}). Saia e entre com a conta certa.`}>
                        <button onClick={() => router.post(`${base}/sair`)} className="ds-btn ds-btn-primary mt-3">
                            <i className="fas fa-sign-out-alt mr-1" />Sair e entrar com outra conta
                        </button>
                    </Mensagem>
                )}
                {estado === 'assinado' && <Assinado {...props} />}
                {estado === 'acesso' && <Acesso convite={convite} base={base} exigeCaptcha={props.exige_captcha} />}
                {estado === 'confirmar_email' && <ConfirmarEmail base={base} email={props.email} />}
                {estado === 'pacote' && <Pacote {...props} base={base} />}
                {estado === 'assinar' && <TelaAssinatura {...props} />}
            </main>
        </div>
    );
}

/* ─── Blocos de estado ─────────────────────────────────────────────────────── */

function CartaoConvite({ convite, pacote }) {
    return (
        <div className="bg-white rounded-xl border border-gray-200 p-5">
            <p className="text-xs text-gray-500">{pacote ? 'Documentos para assinatura' : 'Documento para assinatura'}</p>
            <h1 className="text-lg font-bold text-[#0B1F3B] mt-0.5">{convite.documento}</h1>
            <div className="mt-2 text-sm text-gray-600 space-y-0.5">
                <p><span className="text-gray-400">Para:</span> {convite.nome} · CPF {convite.cpf}</p>
                {convite.solicitante && <p><span className="text-gray-400">Enviado por:</span> {convite.solicitante}{convite.orgao && ` — ${convite.orgao}`}</p>}
                {convite.expira_em && <p><span className="text-gray-400">Assinar até:</span> {convite.expira_em}</p>}
            </div>
            {convite.mensagem && (
                <p className="mt-3 text-sm text-gray-700 bg-gray-50 border-l-4 border-primary-500 rounded px-3 py-2 whitespace-pre-line">{convite.mensagem}</p>
            )}
        </div>
    );
}

function Acesso({ convite, base, exigeCaptcha = false }) {
    const [aba, setAba] = useState('entrar');

    return (
        <div className="bg-white rounded-xl border border-gray-200 max-w-xl mx-auto overflow-hidden">
            <div className="px-5 pt-5">
                <p className="text-sm text-gray-700">
                    Para ver e assinar o documento, entre com a sua conta ou faça um cadastro rápido.
                    O cadastro precisa ser com o CPF <strong>{convite?.cpf}</strong>.
                </p>
            </div>
            <div className="flex border-b border-gray-200 mt-4 px-5 gap-1">
                {[['entrar', 'Já tenho cadastro'], ['cadastrar', 'Quero me cadastrar']].map(([k, l]) => (
                    <button key={k} onClick={() => setAba(k)}
                        className={`px-4 py-2.5 text-sm font-medium border-b-2 -mb-px ${aba === k ? 'border-primary-500 text-primary-600' : 'border-transparent text-gray-500 hover:text-gray-700'}`}>
                        {l}
                    </button>
                ))}
            </div>
            <div className="p-5">
                {aba === 'entrar' ? <FormEntrar base={base} exigeCaptcha={exigeCaptcha} /> : <FormCadastro base={base} convite={convite} />}
            </div>
        </div>
    );
}

function FormEntrar({ base, exigeCaptcha = false }) {
    const { data, setData, post, processing, errors } = useForm({ login: '', senha: '', captcha: '' });

    return (
        <form onSubmit={(e) => { e.preventDefault(); post(`${base}/entrar`); }} className="space-y-3">
            <Campo label="CPF ou e-mail" erro={errors.login}>
                <input value={data.login} onChange={(e) => setData('login', e.target.value)} className="ds-input" autoFocus required autoComplete="username" />
            </Campo>
            <Campo label="Senha" erro={errors.senha}>
                <input type="password" value={data.senha} onChange={(e) => setData('senha', e.target.value)} className="ds-input" required autoComplete="current-password" />
            </Campo>
            {exigeCaptcha && (
                <Captcha contexto="assinatura-entrar" value={data.captcha} onChange={(v) => setData('captcha', v)} error={errors.captcha} />
            )}
            <button type="submit" disabled={processing} className="ds-btn ds-btn-primary w-full justify-center">
                {processing ? <i className="fas fa-spinner fa-spin" /> : 'Entrar'}
            </button>
        </form>
    );
}

function FormCadastro({ base, convite }) {
    const { data, setData, post, processing, errors } = useForm({
        cpf: '', nome: convite?.nome || '', email: '', email_confirmation: '',
        telefone: '', senha: '', senha_confirmation: '', aceite: false, captcha: '',
    });

    return (
        <form onSubmit={(e) => { e.preventDefault(); post(`${base}/cadastrar`); }} className="grid grid-cols-1 sm:grid-cols-2 gap-3">
            <Campo label="CPF" erro={errors.cpf}>
                <input value={data.cpf} onChange={(e) => setData('cpf', mascaraCpf(e.target.value))} className="ds-input" placeholder="000.000.000-00" required inputMode="numeric" />
            </Campo>
            <Campo label="Nome completo" erro={errors.nome}>
                <input value={data.nome} onChange={(e) => setData('nome', e.target.value)} className="ds-input" required autoComplete="name" />
            </Campo>
            <Campo label="E-mail" erro={errors.email}>
                <input type="email" value={data.email} onChange={(e) => setData('email', e.target.value)} className="ds-input" required autoComplete="email" />
            </Campo>
            <Campo label="Confirme o e-mail">
                <input type="email" value={data.email_confirmation} onChange={(e) => setData('email_confirmation', e.target.value)} className="ds-input" required />
            </Campo>
            <Campo label="Celular (opcional)" erro={errors.telefone}>
                <input value={data.telefone} onChange={(e) => setData('telefone', e.target.value)} className="ds-input" placeholder="(31) 90000-0000" inputMode="tel" autoComplete="tel" />
            </Campo>
            <div className="hidden sm:block" />
            <Campo label="Senha (mínimo 8 caracteres)" erro={errors.senha}>
                <input type="password" value={data.senha} onChange={(e) => setData('senha', e.target.value)} className="ds-input" required autoComplete="new-password" />
            </Campo>
            <Campo label="Confirme a senha">
                <input type="password" value={data.senha_confirmation} onChange={(e) => setData('senha_confirmation', e.target.value)} className="ds-input" required autoComplete="new-password" />
            </Campo>
            <div className="sm:col-span-2">
                <label className="flex items-start gap-2 text-sm text-gray-700 cursor-pointer">
                    <input type="checkbox" checked={data.aceite} onChange={(e) => setData('aceite', e.target.checked)} className="mt-0.5" />
                    <span>
                        Li e aceito que a assinatura eletrônica feita com este cadastro tem validade como a minha assinatura
                        (Lei 14.063/2020), e que o meu nome, CPF, IP e data/hora ficam registrados no documento.
                    </span>
                </label>
                {errors.aceite && <p className="text-xs text-red-600 mt-1">{errors.aceite}</p>}
            </div>
            <div className="sm:col-span-2">
                <Captcha contexto="assinatura-cadastro" value={data.captcha} onChange={(v) => setData('captcha', v)} error={errors.captcha} />
            </div>
            <div className="sm:col-span-2">
                <button type="submit" disabled={processing} className="ds-btn ds-btn-primary w-full justify-center">
                    {processing ? <i className="fas fa-spinner fa-spin" /> : 'Criar cadastro'}
                </button>
                <p className="text-xs text-gray-500 mt-2">Depois do cadastro enviamos um código para o seu e-mail.</p>
            </div>
        </form>
    );
}

function ConfirmarEmail({ base, email }) {
    const { data, setData, post, processing, errors } = useForm({ codigo: '' });

    return (
        <div className="bg-white rounded-xl border border-gray-200 max-w-md mx-auto p-6 text-center">
            <i className="fas fa-envelope-open-text text-3xl text-primary-500" />
            <h2 className="text-base font-semibold text-gray-800 mt-2">Confirme o seu e-mail</h2>
            <p className="text-sm text-gray-600 mt-1">Enviamos um código de 6 dígitos para <strong>{email}</strong>.</p>
            <form onSubmit={(e) => { e.preventDefault(); post(`${base}/confirmar-email`); }} className="mt-4 space-y-3">
                <input value={data.codigo} onChange={(e) => setData('codigo', e.target.value.replace(/\D/g, '').slice(0, 6))}
                    className="ds-input text-center text-2xl tracking-[0.5em] font-mono !h-12" inputMode="numeric" autoFocus placeholder="000000" />
                {errors.codigo && <p className="text-xs text-red-600">{errors.codigo}</p>}
                <button type="submit" disabled={processing || data.codigo.length !== 6} className="ds-btn ds-btn-primary w-full justify-center">Confirmar</button>
            </form>
            <button onClick={() => router.post(`${base}/reenviar-codigo`)} className="text-xs text-primary-600 hover:underline mt-3">
                Não recebi — enviar outro código
            </button>
        </div>
    );
}

/* ─── Pacote: vários documentos num link ─────────────────────────────────── */

const SITUACOES = {
    pendente:       ['bg-amber-100 text-amber-800', 'Pendente'],
    aguardando_vez: ['bg-gray-100 text-gray-600', 'Aguardando outras assinaturas'],
    assinado:       ['bg-emerald-100 text-emerald-700', 'Assinado'],
    recusado:       ['bg-red-100 text-red-700', 'Recusado'],
    encerrado:      ['bg-gray-100 text-gray-600', 'Encerrado'],
    expirado:       ['bg-gray-100 text-gray-600', 'Prazo vencido'],
};

function Pacote({ itens = [], base }) {
    const pendentes = itens.filter(i => i.situacao === 'pendente');
    const [todosAberto, setTodosAberto] = useState(false);
    const assinados = itens.filter(i => i.situacao === 'assinado').length;

    return (
        <div className="bg-white rounded-xl border border-gray-200 overflow-hidden">
            <div className="px-5 py-4 border-b border-gray-100 flex flex-wrap items-center justify-between gap-3">
                <div>
                    <p className="text-sm font-semibold text-gray-800">{itens.length} documento(s) — {assinados} assinado(s)</p>
                    <p className="text-xs text-gray-500">Abra cada documento para conferir e assinar, ou assine todos os pendentes de uma vez.</p>
                </div>
                {pendentes.length > 1 && (
                    <button onClick={() => setTodosAberto(true)} className="ds-btn ds-btn-primary">
                        <i className="fas fa-pen-nib mr-1" />Assinar todos os pendentes ({pendentes.length})
                    </button>
                )}
            </div>
            <ul className="divide-y divide-gray-100">
                {itens.map(i => {
                    const [cor, rotulo] = SITUACOES[i.situacao] || SITUACOES.encerrado;
                    return (
                        <li key={i.id} className="px-5 py-3 flex flex-wrap items-center gap-3">
                            <i className="fas fa-file-pdf text-red-400" />
                            <span className="text-sm text-gray-800 flex-1 min-w-[200px]">{i.documento}</span>
                            <span className={`text-[11px] px-2 py-0.5 rounded-full font-medium ${cor}`}>{rotulo}</span>
                            {i.situacao === 'assinado' && i.codigo && (
                                <a href={`/verificar/codigo/${i.codigo}`} target="_blank" rel="noopener" className="text-[11px] font-mono text-primary-600 hover:underline">
                                    {i.codigo}
                                </a>
                            )}
                            {i.link && (
                                <a href={i.link} className="ds-btn ds-btn-outline !h-8 text-sm">
                                    <i className="fas fa-eye mr-1" />Abrir e assinar
                                </a>
                            )}
                        </li>
                    );
                })}
            </ul>
            {todosAberto && <AssinarTodos base={base} pendentes={pendentes} onFechar={() => setTodosAberto(false)} />}
        </div>
    );
}

function AssinarTodos({ base, pendentes, onFechar }) {
    const { errors } = usePage().props;
    const { data, setData, post, processing } = useForm({
        assinatura_ids: pendentes.map(p => p.id),
        canto: 'direita',
        conferido: false,
        senha: '',
    });
    const comQuadro = pendentes.filter(p => p.posicao_sistema).length;

    return (
        <Modal titulo={`Assinar ${pendentes.length} documentos`} onFechar={onFechar}>
            <form onSubmit={(e) => { e.preventDefault(); post(`${base}/assinar-todos`, { preserveScroll: true, onSuccess: onFechar }); }} className="space-y-3">
                <ul className="max-h-40 overflow-y-auto text-sm text-gray-700 bg-gray-50 rounded-lg p-2 space-y-1">
                    {pendentes.map(p => <li key={p.id}><i className="fas fa-file-pdf text-red-400 mr-1.5" />{p.documento}</li>)}
                </ul>
                <div>
                    <p className="text-xs font-semibold text-gray-600 mb-1">Onde vai a assinatura (última página)</p>
                    <div className="grid grid-cols-3 gap-1.5">
                        {[['esquerda', 'Canto esquerdo'], ['centro', 'Centro'], ['direita', 'Canto direito']].map(([k, l]) => (
                            <button key={k} type="button" onClick={() => setData('canto', k)}
                                className={`text-xs px-2 py-2 rounded-lg border ${data.canto === k ? 'bg-primary-500 border-primary-500 text-white' : 'bg-gray-50 border-gray-200 text-gray-700'}`}>
                                {l}
                            </button>
                        ))}
                    </div>
                    <p className="text-[11px] text-gray-500 mt-1">
                        Na parte de baixo da última página; se já houver uma assinatura ali, a sua vai logo acima.
                        {comQuadro > 0 && ` ${comQuadro} documento(s) já têm o lugar da assinatura definido por quem enviou — nesses, ele é usado.`}
                    </p>
                </div>
                <label className="flex items-start gap-2 text-sm text-gray-700 bg-amber-50 border border-amber-200 rounded-lg p-2.5 cursor-pointer">
                    <input type="checkbox" checked={data.conferido} onChange={(e) => setData('conferido', e.target.checked)} className="mt-0.5" />
                    <span>Conferi os <strong>{pendentes.length}</strong> documentos e concordo com o conteúdo de cada um.</span>
                </label>
                {errors?.conferido && <p className="text-xs text-red-600">{errors.conferido}</p>}
                <input type="password" value={data.senha} onChange={(e) => setData('senha', e.target.value)} className="ds-input"
                    placeholder="Sua senha" autoComplete="current-password" required />
                {errors?.senha && <p className="text-xs text-red-600">{errors.senha}</p>}
                <div className="flex gap-2 justify-end">
                    <button type="button" onClick={onFechar} className="ds-btn ds-btn-outline">Voltar</button>
                    <button type="submit" disabled={processing || ! data.conferido || ! data.senha} className="ds-btn ds-btn-primary">
                        {processing ? <><i className="fas fa-spinner fa-spin mr-1" />Assinando…</> : `Assinar ${pendentes.length} documentos`}
                    </button>
                </div>
            </form>
        </Modal>
    );
}

function Assinado({ token, assinado_em, codigo, pode_baixar, pacote_link }) {
    return (
        <div className="bg-white rounded-xl border border-emerald-200 max-w-xl mx-auto p-6 text-center">
            <div className="w-14 h-14 rounded-full bg-emerald-100 flex items-center justify-center mx-auto">
                <i className="fas fa-check text-2xl text-emerald-600" />
            </div>
            <h2 className="text-lg font-semibold text-gray-800 mt-3">Documento assinado</h2>
            <p className="text-sm text-gray-600 mt-1">Assinatura registrada em {assinado_em}. Quem enviou o documento já foi avisado.</p>
            {codigo && (
                <p className="text-sm text-gray-600 mt-3">
                    Código de verificação: <span className="font-mono font-semibold">{codigo}</span><br />
                    <a href={`/verificar/codigo/${codigo}`} target="_blank" rel="noopener" className="text-primary-600 hover:underline text-xs">
                        Conferir a autenticidade
                    </a>
                </p>
            )}
            {pode_baixar && (
                <a href={`/assinar/${token}/baixar`} className="ds-btn ds-btn-primary mt-4 inline-flex">
                    <i className="fas fa-download mr-1" />Baixar o documento assinado
                </a>
            )}
            {pacote_link && (
                <a href={pacote_link} className="ds-btn ds-btn-outline mt-4 ml-2 inline-flex">
                    <i className="fas fa-list mr-1" />Voltar aos documentos
                </a>
            )}
        </div>
    );
}

/* ─── Tela de assinatura: PDF + carimbo ────────────────────────────────────── */

function TelaAssinatura({ token, convite, paginas, carimbo, posicao_sugerida, pacote_link }) {
    const { errors } = usePage().props;
    const totalPaginas = paginas.length;
    const [pdf, setPdf] = useState(null);
    const [erroPdf, setErroPdf] = useState(false);
    const [pagina, setPagina] = useState(posicao_sugerida?.page > 0 ? Math.min(posicao_sugerida.page, totalPaginas) : 1);
    const [rotacao, setRotacao] = useState(0);
    const [caixa, setCaixa] = useState(null); // { pagina, x, y, w, h } em frações da página
    const [confirmando, setConfirmando] = useState(false);
    const [recusando, setRecusando] = useState(false);

    useEffect(() => {
        let cancelado = false;
        garantirWorker();
        pdfjs.getDocument({ url: `/assinar/${token}/pdf`, withCredentials: true }).promise
            .then((doc) => { if (! cancelado) setPdf(doc); })
            .catch(() => { if (! cancelado) setErroPdf(true); });
        return () => { cancelado = true; };
    }, [token]);

    const tamPagina = paginas[pagina - 1];

    // Carimbo em mm → frações da página. Vertical (90/270) troca largura e altura.
    const tamanhoInicial = useCallback((pag, rot) => {
        const tp = paginas[pag - 1];
        const vertical = rot === 90 || rot === 270;
        const wmm = vertical ? carimbo.h : carimbo.w;
        const hmm = vertical ? carimbo.w : carimbo.h;
        return { w: wmm / tp.w, h: hmm / tp.h };
    }, [paginas, carimbo]);

    const adicionar = () => {
        const { w, h } = tamanhoInicial(pagina, rotacao);
        setCaixa({ pagina, x: Math.max(0, 1 - w - 0.06), y: Math.max(0, 1 - h - 0.08), w, h });
    };

    const mudarRotacao = (nova) => {
        const eraVertical = rotacao === 90 || rotacao === 270;
        const ficaVertical = nova === 90 || nova === 270;
        setRotacao(nova);
        if (caixa && eraVertical !== ficaVertical) {
            // Mantém o centro e troca as dimensões (em mm, convertendo pela página).
            const tp = paginas[caixa.pagina - 1];
            const wmm = caixa.h * tp.h, hmm = caixa.w * tp.w;
            const w = wmm / tp.w, h = hmm / tp.h;
            const cx = caixa.x + caixa.w / 2, cy = caixa.y + caixa.h / 2;
            setCaixa({ ...caixa, w, h, x: limitar(cx - w / 2, 0, 1 - w), y: limitar(cy - h / 2, 0, 1 - h) });
        }
    };

    return (
        <div className="grid grid-cols-1 lg:grid-cols-[1fr_300px] gap-4 items-start">
            <div className="bg-white rounded-xl border border-gray-200 overflow-hidden">
                <div className="flex items-center justify-between px-4 py-2.5 border-b border-gray-100 bg-gray-50">
                    <button onClick={() => setPagina(p => Math.max(1, p - 1))} disabled={pagina <= 1}
                        className="ds-btn ds-btn-outline !px-3 disabled:opacity-40" aria-label="Página anterior">
                        <i className="fas fa-chevron-left" />
                    </button>
                    <span className="text-sm font-medium text-gray-700">{pagina} / {totalPaginas}</span>
                    <button onClick={() => setPagina(p => Math.min(totalPaginas, p + 1))} disabled={pagina >= totalPaginas}
                        className="ds-btn ds-btn-outline !px-3 disabled:opacity-40" aria-label="Próxima página">
                        <i className="fas fa-chevron-right" />
                    </button>
                </div>
                <div className="p-3 sm:p-5 bg-gray-100">
                    {erroPdf ? (
                        <p className="text-sm text-red-600 text-center py-10">Não foi possível carregar o PDF. Recarregue a página.</p>
                    ) : ! pdf ? (
                        <p className="text-sm text-gray-500 text-center py-10"><i className="fas fa-spinner fa-spin mr-2" />Carregando o documento…</p>
                    ) : (
                        <PaginaPdf pdf={pdf} numero={pagina} proporcao={tamPagina.h / tamPagina.w}>
                            {(area) => caixa && caixa.pagina === pagina && area.w > 0 && (
                                <CaixaCarimbo caixa={caixa} setCaixa={setCaixa} rotacao={rotacao} carimbo={carimbo} area={area} />
                            )}
                        </PaginaPdf>
                    )}
                </div>
            </div>

            <aside className="bg-white rounded-xl border border-gray-200 p-4 space-y-4 lg:sticky lg:top-4">
                {pacote_link && (
                    <a href={pacote_link} className="text-xs text-primary-600 hover:underline"><i className="fas fa-arrow-left mr-1" />Voltar aos documentos</a>
                )}
                <div>
                    <p className="text-xs text-gray-500">Documento</p>
                    <p className="text-sm font-semibold text-[#0B1F3B]">{convite.documento}</p>
                    <p className="text-xs text-gray-500 mt-1">{convite.nome} · CPF {convite.cpf}</p>
                </div>

                <div>
                    <p className="text-xs font-semibold text-gray-600 mb-2">Orientação da assinatura</p>
                    <div className="grid grid-cols-2 gap-1.5">
                        {ORIENTACOES.map(o => (
                            <button key={o.rotacao} onClick={() => mudarRotacao(o.rotacao)}
                                className={`text-sm px-3 py-2 rounded-lg border transition-colors ${rotacao === o.rotacao
                                    ? 'bg-primary-500 border-primary-500 text-white' : 'bg-gray-50 border-gray-200 text-gray-700 hover:bg-gray-100'}`}>
                                {rotacao === o.rotacao && <i className="fas fa-check text-[10px] mr-1" />}{o.label}
                            </button>
                        ))}
                    </div>
                </div>

                {! caixa ? (
                    <button onClick={adicionar} className="ds-btn ds-btn-primary w-full justify-center">
                        <i className="fas fa-signature mr-1" />Adicionar assinatura
                    </button>
                ) : (
                    <div className="space-y-2">
                        <p className="text-xs text-gray-600 bg-cyan-50 border border-cyan-100 rounded-lg px-3 py-2">
                            Arraste a assinatura até o lugar certo. Use o canto <i className="fas fa-up-right-and-down-left-from-center text-[10px]" /> para mudar o tamanho.
                            {caixa.pagina !== pagina && <> Ela está na página <strong>{caixa.pagina}</strong>.</>}
                        </p>
                        {caixa.pagina !== pagina && (
                            <button onClick={() => setCaixa({ ...caixa, pagina })} className="ds-btn ds-btn-outline w-full justify-center text-sm">
                                Trazer para esta página
                            </button>
                        )}
                        <button onClick={() => setCaixa(null)} className="ds-btn ds-btn-outline w-full justify-center text-sm">
                            <i className="fas fa-eraser mr-1" />Remover e posicionar de novo
                        </button>
                    </div>
                )}

                <button onClick={() => setConfirmando(true)} disabled={! caixa}
                    className="ds-btn ds-btn-primary w-full justify-center !h-11 disabled:opacity-40">
                    <i className="fas fa-pen-nib mr-1" />Assinar
                </button>

                <button onClick={() => setRecusando(true)} className="text-xs text-red-600 hover:underline w-full text-center">
                    Não concordo — recusar assinatura
                </button>
            </aside>

            {confirmando && (
                <ConfirmarAssinatura token={token} caixa={caixa} rotacao={rotacao} voltarPacote={!! pacote_link}
                    onFechar={() => setConfirmando(false)} erroSenha={errors?.senha} />
            )}
            {recusando && <Recusar token={token} onFechar={() => setRecusando(false)} />}
        </div>
    );
}

/** Renderiza uma página do PDF no canvas, na largura do contêiner; os filhos ficam por cima. */
function PaginaPdf({ pdf, numero, proporcao, children }) {
    const canvasRef = useRef(null);
    const wrapRef = useRef(null);
    const [largura, setLargura] = useState(0);

    useEffect(() => {
        const el = wrapRef.current;
        if (! el) return;
        const ro = new ResizeObserver(([e]) => setLargura(Math.floor(e.contentRect.width)));
        ro.observe(el);
        return () => ro.disconnect();
    }, []);

    useEffect(() => {
        if (! largura) return;
        let tarefa = null;
        let cancelado = false;
        pdf.getPage(numero).then((page) => {
            if (cancelado) return;
            const base = page.getViewport({ scale: 1 });
            const dpr = window.devicePixelRatio || 1;
            const viewport = page.getViewport({ scale: (largura / base.width) * dpr });
            const canvas = canvasRef.current;
            canvas.width = viewport.width;
            canvas.height = viewport.height;
            canvas.style.width = `${largura}px`;
            canvas.style.height = `${viewport.height / dpr}px`;
            tarefa = page.render({ canvasContext: canvas.getContext('2d'), viewport });
            tarefa.promise.catch(() => {});
        });
        return () => { cancelado = true; tarefa?.cancel(); };
    }, [pdf, numero, largura]);

    return (
        <div ref={wrapRef} className="max-w-[820px] mx-auto">
            <div className="relative bg-white shadow-md select-none" style={{ height: largura ? largura * proporcao : 400 }}>
                <canvas ref={canvasRef} className="block" />
                <div className="absolute inset-0">{children({ w: largura, h: largura * proporcao })}</div>
            </div>
        </div>
    );
}

/** Carimbo arrastável e redimensionável sobre a página (posição em frações). */
function CaixaCarimbo({ caixa, setCaixa, rotacao, carimbo, area }) {
    const vertical = rotacao === 90 || rotacao === 270;

    const iniciar = (modo) => (e) => {
        e.preventDefault();
        e.stopPropagation();
        const inicio = { px: e.clientX, py: e.clientY, ...caixa };
        const proporcao = caixa.h / caixa.w;

        const mover = (ev) => {
            const dx = (ev.clientX - inicio.px) / area.w;
            const dy = (ev.clientY - inicio.py) / area.h;
            if (modo === 'mover') {
                setCaixa({ ...inicio, x: limitar(inicio.x + dx, 0, 1 - inicio.w), y: limitar(inicio.y + dy, 0, 1 - inicio.h) });
            } else {
                // Redimensiona mantendo a proporção do carimbo.
                const w = limitar(inicio.w + dx, 0.08, 1 - inicio.x);
                const h = w * proporcao;
                if (inicio.y + h <= 1) setCaixa({ ...inicio, w, h });
            }
        };
        const soltar = () => {
            window.removeEventListener('pointermove', mover);
            window.removeEventListener('pointerup', soltar);
        };
        window.addEventListener('pointermove', mover);
        window.addEventListener('pointerup', soltar);
    };

    // Dimensões do texto antes de girar: na vertical, o "comprimento" do carimbo é a altura da caixa.
    const larguraTexto = vertical ? caixa.h * area.h : caixa.w * area.w;
    const alturaTexto = vertical ? caixa.w * area.w : caixa.h * area.h;
    const fonte = Math.max(5, alturaTexto * 0.13);

    return (
        <div onPointerDown={iniciar('mover')}
            className="absolute border-2 border-primary-500 bg-white/85 cursor-move touch-none shadow-sm"
            style={{ left: `${caixa.x * 100}%`, top: `${caixa.y * 100}%`, width: `${caixa.w * 100}%`, height: `${caixa.h * 100}%` }}>
            <div className="absolute left-1/2 top-1/2 overflow-hidden text-gray-800 leading-tight px-1 whitespace-nowrap"
                style={{ width: larguraTexto, height: alturaTexto, fontSize: fonte,
                    transform: `translate(-50%, -50%) rotate(${rotacao}deg)` }}>
                <div className="text-gray-500">Assinado digitalmente por:</div>
                <div className="font-bold truncate" style={{ fontSize: fonte * 1.25 }}>{carimbo.nome}</div>
                <div className="text-gray-600">CPF: {carimbo.cpf} | data e hora</div>
                <div className="text-gray-500">Código de verificação: gerado ao assinar</div>
            </div>
            <span onPointerDown={iniciar('redimensionar')}
                className="absolute -right-2 -bottom-2 w-5 h-5 rounded-full bg-primary-500 text-white flex items-center justify-center cursor-nwse-resize shadow"
                title="Redimensionar">
                <i className="fas fa-up-right-and-down-left-from-center text-[9px]" />
            </span>
        </div>
    );
}

function ConfirmarAssinatura({ token, caixa, rotacao, onFechar, erroSenha, voltarPacote }) {
    const [senha, setSenha] = useState('');
    const [enviando, setEnviando] = useState(false);
    const [geo, setGeo] = useState(null);

    useEffect(() => {
        // Geolocalização é opcional: só entra na evidência se o navegador permitir.
        navigator.geolocation?.getCurrentPosition(
            (p) => setGeo(`${p.coords.latitude.toFixed(5)},${p.coords.longitude.toFixed(5)}`),
            () => {}, { timeout: 4000 },
        );
    }, []);

    const assinar = (e) => {
        e.preventDefault();
        setEnviando(true);
        router.post(`/assinar/${token}/assinar`, {
            senha,
            geolocalizacao: geo,
            posicao: { pagina: caixa.pagina, x: caixa.x, y: caixa.y, w: caixa.w, h: caixa.h, rotacao },
            voltar_pacote: voltarPacote,
        }, { preserveScroll: true, onFinish: () => setEnviando(false) });
    };

    return (
        <Modal titulo="Confirmar assinatura" onFechar={onFechar}>
            <form onSubmit={assinar} className="space-y-3">
                <p className="text-sm text-gray-700">
                    Ao confirmar, a sua assinatura é aplicada na página <strong>{caixa.pagina}</strong> e não pode ser desfeita.
                    Digite a sua senha para validar.
                </p>
                <input type="password" value={senha} onChange={(e) => setSenha(e.target.value)} className="ds-input" autoFocus required
                    placeholder="Sua senha" autoComplete="current-password" />
                {erroSenha && <p className="text-xs text-red-600">{erroSenha}</p>}
                <div className="flex gap-2 justify-end">
                    <button type="button" onClick={onFechar} className="ds-btn ds-btn-outline">Voltar</button>
                    <button type="submit" disabled={enviando || ! senha} className="ds-btn ds-btn-primary">
                        {enviando ? <><i className="fas fa-spinner fa-spin mr-1" />Assinando…</> : 'Confirmar assinatura'}
                    </button>
                </div>
            </form>
        </Modal>
    );
}

function Recusar({ token, onFechar }) {
    const { data, setData, post, processing, errors } = useForm({ motivo: '' });

    return (
        <Modal titulo="Recusar assinatura" onFechar={onFechar}>
            <form onSubmit={(e) => { e.preventDefault(); post(`/assinar/${token}/recusar`); }} className="space-y-3">
                <p className="text-sm text-gray-700">Conte o motivo. Quem enviou o documento será avisado e a solicitação é encerrada.</p>
                <textarea value={data.motivo} onChange={(e) => setData('motivo', e.target.value)} rows={3}
                    className="ds-input !h-auto" required minLength={5} maxLength={500} />
                {errors.motivo && <p className="text-xs text-red-600">{errors.motivo}</p>}
                <div className="flex gap-2 justify-end">
                    <button type="button" onClick={onFechar} className="ds-btn ds-btn-outline">Voltar</button>
                    <button type="submit" disabled={processing} className="ds-btn ds-btn-danger">Recusar</button>
                </div>
            </form>
        </Modal>
    );
}

/* ─── Apoio ───────────────────────────────────────────────────────────────── */

function Modal({ titulo, onFechar, children }) {
    useEffect(() => {
        const esc = (e) => e.key === 'Escape' && onFechar();
        window.addEventListener('keydown', esc);
        return () => window.removeEventListener('keydown', esc);
    }, [onFechar]);

    return (
        <div className="fixed inset-0 z-50 bg-black/40 flex items-center justify-center p-4" onClick={onFechar}>
            <div className="bg-white rounded-xl shadow-xl w-full max-w-md p-5" onClick={(e) => e.stopPropagation()} role="dialog" aria-label={titulo}>
                <h3 className="text-base font-semibold text-gray-800 mb-3">{titulo}</h3>
                {children}
            </div>
        </div>
    );
}

function Mensagem({ icone, tom, titulo, texto, children }) {
    const cores = {
        erro: 'text-red-600 bg-red-50', aviso: 'text-amber-600 bg-amber-50', info: 'text-primary-600 bg-cyan-50',
    }[tom];
    return (
        <div className="bg-white rounded-xl border border-gray-200 max-w-xl mx-auto p-6 text-center">
            <div className={`w-12 h-12 rounded-full flex items-center justify-center mx-auto ${cores}`}>
                <i className={`fas ${icone} text-xl`} />
            </div>
            <h2 className="text-base font-semibold text-gray-800 mt-3">{titulo}</h2>
            <p className="text-sm text-gray-600 mt-1">{texto}</p>
            {children}
        </div>
    );
}

function Aviso({ tom, children }) {
    const cls = tom === 'sucesso' ? 'bg-emerald-50 border-emerald-200 text-emerald-800' : 'bg-red-50 border-red-200 text-red-800';
    return <div className={`border rounded-lg px-4 py-2.5 text-sm ${cls}`}>{children}</div>;
}

function Campo({ label, erro, children }) {
    return (
        <div>
            <label className="text-xs font-semibold text-gray-600 mb-1 block">{label}</label>
            {children}
            {erro && <p className="text-xs text-red-600 mt-1">{erro}</p>}
        </div>
    );
}

function limitar(v, min, max) {
    return Math.min(Math.max(v, min), max);
}

function mascaraCpf(v) {
    const d = v.replace(/\D/g, '').slice(0, 11);
    return d.replace(/(\d{3})(\d)/, '$1.$2').replace(/(\d{3})(\d)/, '$1.$2').replace(/(\d{3})(\d{1,2})$/, '$1-$2');
}

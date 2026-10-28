/**
 * Meu perfil › Segurança — verificação em duas etapas por aplicativo
 * autenticador (TOTP): ativar pelo QR code, códigos de recuperação, desativar.
 */
import { Head, Link, router, useForm } from '@inertiajs/react';
import { useState } from 'react';
import AdminLayout from '../../../Layouts/AdminLayout';
import PageHeader from '../../../Components/PageHeader';
import PerfilAbas from '../../../Components/PerfilAbas';
import Button from '../../../Components/Button';

export default function Seguranca({ dois_fatores, configurando, codigos_recuperacao }) {
    return (
        <AdminLayout>
            <Head title="Segurança" />
            <PageHeader title="Meu perfil" subtitle="Verificação em duas etapas e certificados digitais" />
            <PerfilAbas />

            <div className="grid grid-cols-1 lg:grid-cols-3 gap-4">
                <div className="lg:col-span-2 space-y-4">
                    {codigos_recuperacao && <CodigosRecuperacao codigos={codigos_recuperacao} />}
                    <DoisFatores estado={dois_fatores} configurando={configurando} />
                </div>
                <div className="ds-card p-5 h-fit">
                    <p className="text-sm font-semibold text-gray-800 mb-1"><i className="fas fa-id-card mr-2 text-cyan-600" />Certificado digital</p>
                    <p className="text-xs text-gray-500 mb-3">Certificados ICP-Brasil vinculados à sua conta, usados para assinar.</p>
                    <Link href="/perfil/certificados" className="ds-btn ds-btn-outline ds-btn-sm">Meus certificados</Link>
                </div>
            </div>
        </AdminLayout>
    );
}

function DoisFatores({ estado, configurando }) {
    if (estado.ativo) return <DoisFatoresAtivo estado={estado} />;

    return (
        <div className="ds-card p-5">
            <div className="flex items-start justify-between gap-4">
                <div>
                    <p className="text-sm font-semibold text-gray-800"><i className="fas fa-mobile-alt mr-2 text-cyan-600" />Verificação em duas etapas</p>
                    <p className="text-xs text-gray-500 mt-1 max-w-xl">
                        Além da senha, a entrada pede um código de 6 dígitos que muda a cada 30 segundos no aplicativo
                        autenticador do celular (Google Authenticator, Microsoft Authenticator, Authy e outros).
                    </p>
                </div>
                <span className="text-[11px] px-2.5 py-1 rounded-full bg-gray-100 text-gray-600 font-medium whitespace-nowrap">Desativada</span>
            </div>
            {configurando ? <Configurar configurando={configurando} /> : (
                <Button className="mt-4" icon="fas fa-shield-alt" onClick={() => router.post('/perfil/seguranca/dois-fatores/iniciar')}>
                    Ativar
                </Button>
            )}
        </div>
    );
}

function Configurar({ configurando }) {
    const { data, setData, post, processing, errors } = useForm({ codigo: '' });
    return (
        <div className="mt-4 grid grid-cols-1 md:grid-cols-[auto_1fr] gap-5 items-start border-t border-gray-100 pt-4">
            <div className="bg-white border border-gray-200 rounded-xl p-2 w-48 h-48 [&_svg]:w-full [&_svg]:h-full"
                dangerouslySetInnerHTML={{ __html: configurando.qr }} />
            <div className="space-y-3 text-sm text-gray-700">
                <p><b>1.</b> No aplicativo autenticador, toque em <i>adicionar</i> e leia o QR code.</p>
                <p className="text-xs text-gray-500">Sem câmera? Digite a chave: <code className="font-mono text-gray-800 bg-gray-50 px-1.5 py-0.5 rounded">{configurando.chave}</code></p>
                <p><b>2.</b> Digite o código de 6 dígitos que o aplicativo mostrar:</p>
                <form onSubmit={(e) => { e.preventDefault(); post('/perfil/seguranca/dois-fatores/ativar'); }} className="flex gap-2 items-start">
                    <div>
                        <input value={data.codigo} onChange={(e) => setData('codigo', e.target.value)} inputMode="numeric" maxLength={6}
                            autoComplete="one-time-code" placeholder="000000" className="ds-input w-36 text-center font-mono tracking-widest" autoFocus />
                        {errors.codigo && <p className="text-xs text-red-600 mt-1">{errors.codigo}</p>}
                    </div>
                    <Button type="submit" loading={processing} disabled={data.codigo.length < 6}>Confirmar e ativar</Button>
                </form>
            </div>
        </div>
    );
}

function DoisFatoresAtivo({ estado }) {
    const [acao, setAcao] = useState(null); // 'desativar' | 'codigos'
    const { data, setData, post, processing, errors, reset } = useForm({ senha: '' });
    const enviar = (e) => {
        e.preventDefault();
        post(`/perfil/seguranca/dois-fatores/${acao}`, { onSuccess: () => { reset(); setAcao(null); } });
    };
    return (
        <div className="ds-card p-5">
            <div className="flex items-start justify-between gap-4">
                <div>
                    <p className="text-sm font-semibold text-gray-800"><i className="fas fa-mobile-alt mr-2 text-cyan-600" />Verificação em duas etapas</p>
                    <p className="text-xs text-gray-500 mt-1">Ativada em {estado.ativado_em}. Restam {estado.restantes} código(s) de recuperação.</p>
                </div>
                <span className="text-[11px] px-2.5 py-1 rounded-full bg-emerald-100 text-emerald-700 font-medium whitespace-nowrap">
                    <i className="fas fa-check mr-1" />Ativada
                </span>
            </div>
            {!acao ? (
                <div className="mt-4 flex flex-wrap gap-2">
                    <Button variant="secondary" size="sm" icon="fas fa-key" onClick={() => setAcao('codigos')}>Gerar novos códigos de recuperação</Button>
                    <Button variant="danger" size="sm" icon="fas fa-times" onClick={() => setAcao('desativar')}>Desativar</Button>
                </div>
            ) : (
                <form onSubmit={enviar} className="mt-4 flex flex-wrap gap-2 items-start border-t border-gray-100 pt-4">
                    <div>
                        <input type="password" value={data.senha} onChange={(e) => setData('senha', e.target.value)} placeholder="Sua senha"
                            className="ds-input w-56" autoFocus autoComplete="current-password" />
                        {errors.senha && <p className="text-xs text-red-600 mt-1">{errors.senha}</p>}
                    </div>
                    <Button type="submit" variant={acao === 'desativar' ? 'danger' : 'primary'} loading={processing}>
                        {acao === 'desativar' ? 'Confirmar desativação' : 'Gerar códigos'}
                    </Button>
                    <Button type="button" variant="secondary" onClick={() => { setAcao(null); reset(); }}>Cancelar</Button>
                </form>
            )}
        </div>
    );
}

function CodigosRecuperacao({ codigos }) {
    return (
        <div className="ds-card p-5 border-amber-200 bg-amber-50/50">
            <p className="text-sm font-semibold text-amber-800"><i className="fas fa-exclamation-triangle mr-2" />Guarde estes códigos de recuperação</p>
            <p className="text-xs text-amber-700 mt-1">
                Se perder o celular, cada código permite entrar uma vez. Eles <b>não serão mostrados de novo</b>.
            </p>
            <div className="grid grid-cols-2 sm:grid-cols-4 gap-2 mt-3">
                {codigos.map((c) => (
                    <code key={c} className="font-mono text-sm bg-white border border-amber-200 rounded-lg px-3 py-1.5 text-center">{c}</code>
                ))}
            </div>
            <button type="button" onClick={() => navigator.clipboard?.writeText(codigos.join('\n'))}
                className="mt-3 text-xs text-amber-800 hover:underline"><i className="fas fa-copy mr-1" />Copiar todos</button>
        </div>
    );
}

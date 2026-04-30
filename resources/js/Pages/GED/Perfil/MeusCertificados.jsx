/**
 * Meus Certificados ICP-Brasil — perfil do usuario
 *
 * Tela para o usuario gerenciar os certificados publicos vinculados ao seu
 * perfil. O cadastro pede um .pfx + senha, mas APENAS o cert publico e
 * armazenado — a chave privada e a senha sao descartadas imediatamente.
 */
import { Head, useForm } from '@inertiajs/react';
import { useState } from 'react';
import AdminLayout from '../../../Layouts/AdminLayout';
import PageHeader from '../../../Components/PageHeader';
import Button from '../../../Components/Button';
import Card from '../../../Components/Card';
import Modal from '../../../Components/Modal';

export default function MeusCertificados({ certificados, usuario_cpf }) {
    const [novo, setNovo] = useState(false);
    const [confirma, setConfirma] = useState(null); // { acao: 'inativar'|'reativar', cert }

    return (
        <AdminLayout>
            <Head title="Meus Certificados" />
            <PageHeader
                title="Meus Certificados ICP-Brasil"
                subtitle="Gerencie os certificados digitais vinculados ao seu perfil"
            />

            {/* Aviso CPF */}
            {!usuario_cpf && (
                <div className="bg-amber-50 border border-amber-200 rounded-xl p-3 mb-4 flex items-start gap-2">
                    <i className="fas fa-exclamation-triangle text-amber-600 mt-0.5" />
                    <div>
                        <p className="text-xs font-semibold text-amber-800">CPF nao cadastrado no seu perfil</p>
                        <p className="text-[11px] text-amber-700">
                            Sem o CPF cadastrado, nao podemos verificar se o certificado pertence a voce.
                            Peca ao admin para cadastrar seu CPF no usuario.
                        </p>
                    </div>
                </div>
            )}

            {/* Aviso de seguranca */}
            <div className="bg-blue-50 border border-blue-200 rounded-xl p-3 mb-4 flex items-start gap-2">
                <i className="fas fa-shield-alt text-blue-600 mt-0.5" />
                <div className="flex-1">
                    <p className="text-xs font-semibold text-blue-800">A chave privada nunca e armazenada</p>
                    <p className="text-[11px] text-blue-700 leading-relaxed">
                        Ao cadastrar um certificado A1, voce envia o .pfx com a senha.
                        O servidor abre o arquivo em memoria, valida a cadeia ICP-Brasil e o CPF,
                        salva apenas o certificado publico, e descarta imediatamente o material privado.
                        No momento de cada assinatura voce ainda precisara fornecer o .pfx + senha.
                    </p>
                </div>
            </div>

            <div className="flex justify-between items-center mb-4">
                <p className="text-xs text-gray-500">
                    {certificados.length} certificado(s) vinculado(s)
                </p>
                <Button onClick={() => setNovo(true)} icon="fas fa-plus">
                    Cadastrar certificado A1
                </Button>
            </div>

            {certificados.length === 0 ? (
                <Card>
                    <div className="py-12 text-center text-gray-400">
                        <i className="fas fa-id-card text-4xl mb-3 block" />
                        <p className="text-sm">Nenhum certificado cadastrado</p>
                        <p className="text-xs mt-1">
                            Clique em "Cadastrar certificado A1" para validar e vincular um certificado ICP-Brasil ao seu perfil.
                        </p>
                    </div>
                </Card>
            ) : (
                <div className="grid grid-cols-1 md:grid-cols-2 gap-3">
                    {certificados.map(c => (
                        <CertCard
                            key={c.id}
                            cert={c}
                            onInativar={() => setConfirma({ acao: 'inativar', cert: c })}
                            onReativar={() => setConfirma({ acao: 'reativar', cert: c })}
                        />
                    ))}
                </div>
            )}

            <NovoCertificadoModal show={novo} onClose={() => setNovo(false)} />
            <ConfirmaModal confirma={confirma} onClose={() => setConfirma(null)} />
        </AdminLayout>
    );
}

function CertCard({ cert, onInativar, onReativar }) {
    let badge = { label: 'Ativo',     classe: 'bg-green-100 text-green-700' };
    if (cert.revogado)      badge = { label: 'Inativo',   classe: 'bg-gray-100 text-gray-600' };
    else if (cert.expirado) badge = { label: 'Expirado',  classe: 'bg-red-100 text-red-700' };
    else if (cert.dias_para_expirar !== null && cert.dias_para_expirar < 30) {
        badge = { label: `Expira em ${cert.dias_para_expirar}d`, classe: 'bg-amber-100 text-amber-700' };
    }

    return (
        <div className={`bg-white rounded-2xl shadow-sm border p-4 ${cert.revogado ? 'opacity-60' : 'border-gray-100'}`}>
            <div className="flex items-start justify-between mb-3">
                <div className="flex items-center gap-2">
                    <div className={`w-10 h-10 rounded-xl flex items-center justify-center ${
                        cert.tipo === 'A3' ? 'bg-purple-100' : 'bg-blue-100'
                    }`}>
                        <i className={`fas ${cert.tipo === 'A3' ? 'fa-usb text-purple-600' : 'fa-id-card text-blue-600'} text-sm`} />
                    </div>
                    <div>
                        <p className="text-sm font-semibold text-gray-800">{cert.subject_cn || '?'}</p>
                        <p className="text-[11px] text-gray-500">
                            {cert.tipo} {cert.icp_brasil && '· ICP-Brasil'}
                        </p>
                    </div>
                </div>
                <span className={`text-[10px] px-2 py-0.5 rounded-full font-bold ${badge.classe}`}>
                    {badge.label}
                </span>
            </div>

            <div className="space-y-1 text-[11px] text-gray-600 mb-3">
                {cert.subject_cpf && (
                    <Linha label="CPF" valor={mascararCpf(cert.subject_cpf)} />
                )}
                <Linha label="AC emissora" valor={cert.issuer_cn} />
                <Linha label="Validade" valor={`${formatarData(cert.valido_de)} ate ${formatarData(cert.valido_ate)}`} />
                <Linha label="Numero serie" valor={cert.serial_number?.slice(0, 24) + (cert.serial_number?.length > 24 ? '...' : '')} mono />
                <Linha label="Thumbprint" valor={cert.thumbprint_sha256?.slice(0, 16) + '...'} mono />
                {cert.assinaturas_count > 0 && (
                    <Linha label="Usado em" valor={`${cert.assinaturas_count} assinatura(s)`} />
                )}
            </div>

            <div className="flex justify-end gap-2 pt-2 border-t border-gray-100">
                {cert.revogado ? (
                    <button onClick={onReativar}
                        className="text-[11px] px-3 py-1 rounded-lg text-blue-600 hover:bg-blue-50 transition-colors">
                        <i className="fas fa-undo mr-1" />
                        Reativar
                    </button>
                ) : (
                    <button onClick={onInativar}
                        className="text-[11px] px-3 py-1 rounded-lg text-red-600 hover:bg-red-50 transition-colors">
                        <i className="fas fa-times mr-1" />
                        Inativar
                    </button>
                )}
            </div>
        </div>
    );
}

function Linha({ label, valor, mono }) {
    return (
        <div className="flex items-start gap-2">
            <span className="text-gray-400 min-w-[80px] uppercase tracking-wide text-[9px] mt-0.5">{label}</span>
            <span className={`text-gray-800 break-all flex-1 ${mono ? 'font-mono text-[10px]' : ''}`}>
                {valor || '-'}
            </span>
        </div>
    );
}

function NovoCertificadoModal({ show, onClose }) {
    const { data, setData, post, processing, errors, reset } = useForm({
        pfx: null,
        senha: '',
    });

    const submit = (e) => {
        e.preventDefault();
        post('/perfil/certificados', {
            forceFormData: true,
            onSuccess: () => { reset(); onClose(); },
        });
    };

    return (
        <Modal show={show} onClose={onClose} title="Cadastrar certificado A1">
            <form onSubmit={submit} className="space-y-4">
                <div className="bg-blue-50 border border-blue-200 rounded-xl p-3">
                    <p className="text-[11px] text-blue-800 leading-tight">
                        <i className="fas fa-info-circle mr-1" />
                        O servidor vai abrir seu .pfx em memoria, validar a cadeia ICP-Brasil e o CPF,
                        salvar apenas o certificado publico, e descartar imediatamente a senha e a chave privada.
                    </p>
                </div>

                <div>
                    <label className="block text-sm font-medium text-gray-700 mb-1">
                        Arquivo .pfx ou .p12
                    </label>
                    <input type="file" accept=".pfx,.p12"
                        onChange={(e) => setData('pfx', e.target.files?.[0] ?? null)}
                        className="block w-full text-sm text-gray-600 file:mr-3 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-xs file:font-medium file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100" />
                    {errors.pfx && <p className="mt-1 text-xs text-red-600">{errors.pfx}</p>}
                </div>

                <div>
                    <label className="block text-sm font-medium text-gray-700 mb-1">Senha</label>
                    <input type="password" value={data.senha} onChange={(e) => setData('senha', e.target.value)}
                        autoComplete="off" className="ds-input" placeholder="Senha do certificado" />
                    {errors.senha && <p className="mt-1 text-xs text-red-600">{errors.senha}</p>}
                </div>

                <div className="flex justify-end gap-2 pt-2">
                    <Button variant="secondary" type="button" onClick={onClose}>Cancelar</Button>
                    <Button type="submit" loading={processing} icon="fas fa-shield-alt"
                        disabled={!data.pfx || !data.senha}>
                        Validar e cadastrar
                    </Button>
                </div>
            </form>
        </Modal>
    );
}

function ConfirmaModal({ confirma, onClose }) {
    const { post, processing } = useForm();

    if (!confirma) return null;

    const isInativar = confirma.acao === 'inativar';

    const submit = () => {
        post(`/perfil/certificados/${confirma.cert.id}/${confirma.acao}`, {
            onSuccess: onClose,
        });
    };

    return (
        <Modal show={!!confirma} onClose={onClose}
            title={isInativar ? 'Inativar certificado' : 'Reativar certificado'}>
            <div className="space-y-4">
                <p className="text-sm text-gray-700">
                    {isInativar
                        ? 'Voce esta marcando este certificado como inativo. Ele nao aparecera mais nas listagens, mas as assinaturas que voce ja fez com ele continuam validas.'
                        : 'Reativar este certificado. Ele voltara a aparecer nas listagens normalmente.'}
                </p>
                <div className="bg-gray-50 rounded-lg p-3">
                    <p className="text-xs font-semibold text-gray-700">{confirma.cert.subject_cn}</p>
                    <p className="text-[10px] text-gray-500 font-mono">{confirma.cert.thumbprint_sha256?.slice(0, 32)}...</p>
                </div>
                <div className="flex justify-end gap-2 pt-2">
                    <Button variant="secondary" type="button" onClick={onClose}>Cancelar</Button>
                    <Button variant={isInativar ? 'danger' : 'primary'} type="button" onClick={submit} loading={processing}>
                        {isInativar ? 'Inativar' : 'Reativar'}
                    </Button>
                </div>
            </div>
        </Modal>
    );
}

function mascararCpf(cpf) {
    const digits = (cpf || '').replace(/\D/g, '');
    if (digits.length !== 11) return cpf;
    return `${digits.slice(0,3)}.***.***-${digits.slice(-2)}`;
}

function formatarData(iso) {
    if (!iso) return '-';
    try {
        const [a, m, d] = iso.split('-');
        return `${d}/${m}/${a}`;
    } catch { return iso; }
}

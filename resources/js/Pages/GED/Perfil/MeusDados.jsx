/**
 * Meu perfil › Meus dados — foto, contato (e-mail, e-mail secundário,
 * celular) e senha. Nome, CPF, matrícula, cargo e lotação vêm do cadastro
 * do administrador (TR 4.7.12).
 */
import { Head, router, useForm, usePage } from '@inertiajs/react';
import { useRef } from 'react';
import AdminLayout from '../../../Layouts/AdminLayout';
import PageHeader from '../../../Components/PageHeader';
import PerfilAbas from '../../../Components/PerfilAbas';
import Button from '../../../Components/Button';

const SEXO = { F: 'Feminino', M: 'Masculino', O: 'Outro / prefere não informar' };

export default function MeusDados({ perfil }) {
    return (
        <AdminLayout>
            <Head title="Meu perfil" />
            <PageHeader title="Meu perfil" subtitle="Seus dados de contato, foto e senha" />
            <PerfilAbas />

            <div className="grid grid-cols-1 lg:grid-cols-3 gap-4">
                <div className="space-y-4">
                    <Foto perfil={perfil} />
                    <Cadastro perfil={perfil} />
                </div>
                <div className="lg:col-span-2 space-y-4">
                    <Contato perfil={perfil} />
                    <Senha />
                </div>
            </div>
        </AdminLayout>
    );
}

function Foto({ perfil }) {
    const entrada = useRef(null);
    const { errors } = usePage().props;
    const iniciais = perfil.name.split(' ').filter(Boolean).slice(0, 2).map((p) => p[0]).join('').toUpperCase();
    const enviar = (e) => {
        const arquivo = e.target.files?.[0];
        if (arquivo) router.post('/perfil/foto', { foto: arquivo }, { forceFormData: true, preserveScroll: true });
        e.target.value = '';
    };
    return (
        <div className="ds-card p-5 text-center">
            {perfil.foto_url
                ? <img src={perfil.foto_url} alt="Sua foto" className="w-32 h-32 rounded-full object-cover mx-auto border-4 border-white shadow" />
                : <div className="w-32 h-32 rounded-full mx-auto bg-gradient-to-br from-cyan-500 to-blue-600 text-white text-3xl font-bold flex items-center justify-center">{iniciais}</div>}
            <p className="text-sm font-semibold text-gray-800 mt-3">{perfil.name}</p>
            <p className="text-xs text-gray-500">A foto aparece para os colegas na tramitação dos documentos.</p>
            <input ref={entrada} type="file" accept="image/jpeg,image/png" className="hidden" onChange={enviar} />
            <div className="flex justify-center gap-2 mt-3">
                <Button size="sm" variant="secondary" icon="fas fa-camera" onClick={() => entrada.current?.click()}>
                    {perfil.foto_url ? 'Trocar foto' : 'Enviar foto'}
                </Button>
                {perfil.foto_url && (
                    <Button size="sm" variant="secondary" icon="fas fa-trash" onClick={() => router.post('/perfil/foto/remover', {}, { preserveScroll: true })}>
                        Remover
                    </Button>
                )}
            </div>
            {errors.foto && <p className="text-xs text-red-600 mt-2">{errors.foto}</p>}
        </div>
    );
}

function Cadastro({ perfil }) {
    const linhas = [
        ['CPF', perfil.cpf], ['Matrícula', perfil.matricula], ['Cargo / função', perfil.cargo],
        ['Lotação', perfil.lotacao], ['Sexo', SEXO[perfil.sexo]], ['Nascimento', perfil.data_nascimento],
    ];
    return (
        <div className="ds-card p-5">
            <p className="text-sm font-semibold text-gray-800 mb-3"><i className="fas fa-id-badge mr-2 text-cyan-600" />Cadastro</p>
            <dl className="space-y-2 text-sm">
                {linhas.map(([rotulo, valor]) => (
                    <div key={rotulo} className="flex justify-between gap-3">
                        <dt className="text-gray-500">{rotulo}</dt>
                        <dd className="text-gray-800 text-right">{valor || <span className="text-gray-300">—</span>}</dd>
                    </div>
                ))}
            </dl>
            <p className="text-[11px] text-gray-400 mt-3">Para corrigir nome, CPF, matrícula, cargo ou lotação, procure o administrador.</p>
        </div>
    );
}

function Contato({ perfil }) {
    const { data, setData, post, processing, errors, isDirty } = useForm({
        email: perfil.email || '', email_secundario: perfil.email_secundario || '', telefone: perfil.telefone || '', senha_atual: '',
    });
    const trocouEmail = data.email.trim().toLowerCase() !== (perfil.email || '').toLowerCase();
    return (
        <form onSubmit={(e) => { e.preventDefault(); post('/perfil/dados', { preserveScroll: true, onSuccess: () => setData('senha_atual', '') }); }}
            className="ds-card p-5 space-y-4">
            <p className="text-sm font-semibold text-gray-800"><i className="fas fa-envelope mr-2 text-cyan-600" />Contato</p>
            <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                <Campo rotulo="E-mail (entrada e recuperação de senha)" erro={errors.email}>
                    <input type="email" value={data.email} onChange={(e) => setData('email', e.target.value)} className="ds-input" required />
                </Campo>
                <Campo rotulo="E-mail secundário (recebe cópia dos avisos)" erro={errors.email_secundario}>
                    <input type="email" value={data.email_secundario} onChange={(e) => setData('email_secundario', e.target.value)} className="ds-input" />
                </Campo>
                <Campo rotulo="Celular" erro={errors.telefone}>
                    <input value={data.telefone} onChange={(e) => setData('telefone', e.target.value)} className="ds-input" inputMode="tel" placeholder="(00) 00000-0000" />
                </Campo>
                {trocouEmail && (
                    <Campo rotulo="Sua senha atual (para trocar o e-mail)" erro={errors.senha_atual}>
                        <input type="password" value={data.senha_atual} onChange={(e) => setData('senha_atual', e.target.value)} className="ds-input" autoComplete="current-password" />
                    </Campo>
                )}
            </div>
            <div className="flex justify-end">
                <Button type="submit" loading={processing} disabled={!isDirty} icon="fas fa-save">Salvar</Button>
            </div>
        </form>
    );
}

function Senha() {
    const { data, setData, post, processing, errors, reset } = useForm({ senha_atual: '', senha: '', senha_confirmation: '' });
    return (
        <form onSubmit={(e) => { e.preventDefault(); post('/perfil/senha', { preserveScroll: true, onSuccess: () => reset() }); }}
            className="ds-card p-5 space-y-4">
            <p className="text-sm font-semibold text-gray-800"><i className="fas fa-key mr-2 text-cyan-600" />Alterar senha</p>
            <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
                <Campo rotulo="Senha atual" erro={errors.senha_atual}>
                    <input type="password" value={data.senha_atual} onChange={(e) => setData('senha_atual', e.target.value)} className="ds-input" autoComplete="current-password" />
                </Campo>
                <Campo rotulo="Nova senha" erro={errors.senha}>
                    <input type="password" value={data.senha} onChange={(e) => setData('senha', e.target.value)} className="ds-input" autoComplete="new-password" />
                </Campo>
                <Campo rotulo="Repita a nova senha">
                    <input type="password" value={data.senha_confirmation} onChange={(e) => setData('senha_confirmation', e.target.value)} className="ds-input" autoComplete="new-password" />
                </Campo>
            </div>
            <div className="flex items-center justify-between">
                <p className="text-[11px] text-gray-400">Mínimo de 8 caracteres, com letras e números.</p>
                <Button type="submit" loading={processing} disabled={!data.senha_atual || !data.senha} icon="fas fa-check">Alterar senha</Button>
            </div>
        </form>
    );
}

function Campo({ rotulo, erro, children }) {
    return (
        <div>
            <label className="block text-xs font-medium text-gray-600 mb-1">{rotulo}</label>
            {children}
            {erro && <p className="text-xs text-red-600 mt-1">{erro}</p>}
        </div>
    );
}

/**
 * Nova Circular — GED
 *
 * Formulario de criacao de circular interna.
 */
import { Head, router, useForm, usePage } from '@inertiajs/react';
import EmNomeDe from '../../../Components/EmNomeDe';
import { useState } from 'react';
import AdminLayout from '../../../Layouts/AdminLayout';
import PageHeader from '../../../Components/PageHeader';
import Button from '../../../Components/Button';
import RichEditor from '../../../Components/RichEditor';
import SetoresMulti from '../../../Components/SetoresMulti';
import useRascunho from '../../../Hooks/useRascunho';
import FerramentasDoTexto from '../../../Components/FerramentasDoTexto';
import { AnexosRascunho, EstadoRascunho, comRascunho, dadosDoRascunho } from '../../../Components/RascunhoAutomatico';

export default function CircularesCreate({ usuarios, autorizacoes = [], variaveis = [], rascunho = null, unidades = [], opcoes_respostas = {} }) {
    const userList = usuarios || [];
    const [searchUser, setSearchUser] = useState('');

    const { data, setData, post, processing, errors } = useForm(comRascunho(rascunho, {
        assunto: '',
        destino_tipo: 'todos',
        destinatarios: [],
        destino_setores: [],
        conteudo: '',
        setor_origem: '',
        em_nome_de: '',
        contrassenha: '',
        data_arquivamento_auto: '',
        urgente: false,
        respostas: 'abertas',
        files: [],
    }));

    // Rascunho automático do setor (TR 4.16.6–4.16.8)
    const r = useRascunho({ modulo: 'circular', dados: dadosDoRascunho(data), titulo: data.assunto, inicial: rascunho, temConteudo: !! (data.assunto || data.conteudo) });

    const filteredUsers = userList.filter(u =>
        u.name.toLowerCase().includes(searchUser.toLowerCase()) ||
        u.email.toLowerCase().includes(searchUser.toLowerCase())
    );

    const toggleDestinatario = (id) => {
        const list = data.destinatarios.includes(id)
            ? data.destinatarios.filter(x => x !== id)
            : [...data.destinatarios, id];
        setData('destinatarios', list);
    };

    const submit = (e) => {
        e.preventDefault();

        const formData = new FormData();
        formData.append('assunto', data.assunto);
        formData.append('conteudo', data.conteudo);
        formData.append('destino_tipo', data.destino_tipo);
        formData.append('setor_origem', data.setor_origem);
        if (data.em_nome_de) {
            formData.append('em_nome_de', data.em_nome_de);
            formData.append('contrassenha', data.contrassenha);
        }
        formData.append('data_arquivamento_auto', data.data_arquivamento_auto);
        formData.append('urgente', data.urgente ? '1' : '0');
        formData.append('respostas', data.respostas);

        if (data.destino_tipo === 'usuarios') {
            data.destinatarios.forEach((id, i) => {
                formData.append(`destinatarios[${i}]`, id);
            });
        }

        if (data.destino_tipo === 'setores') {
            data.destino_setores.filter((v) => Number.isInteger(Number(v))).forEach((id, i) => {
                formData.append(`destino_setores[${i}]`, id);
            });
        }

        if (data.files.length > 0) {
            Array.from(data.files).forEach((file, i) => {
                formData.append(`files[${i}]`, file);
            });
        }

        if (r.id) formData.append('rascunho_id', r.id);

        router.post('/circulares', formData, {
            forceFormData: true,
        });
    };

    return (
        <AdminLayout>
            <Head title="Nova Circular" />
            <PageHeader
                title="Nova Circular"
                subtitle="Criar e enviar uma circular interna"
            >
                <Button variant="secondary" icon="fas fa-arrow-left" href="/circulares">Voltar</Button>
            </PageHeader>

            <div className="bg-white rounded-xl border border-gray-200 p-6">
                <form onSubmit={submit} className="space-y-6">
                    <EstadoRascunho r={r} />
                    <fieldset disabled={!! r.travadoPor} className="space-y-6 min-w-0">
                            <EmNomeDe autorizacoes={autorizacoes} data={data} setData={setData} errors={usePage().props.errors} />
                    {/* Assunto */}
                    <div>
                        <label className="block text-sm font-medium text-gray-700 mb-1">
                            Assunto <span className="text-red-500">*</span>
                        </label>
                        <input
                            type="text"
                            value={data.assunto}
                            onChange={(e) => setData('assunto', e.target.value)}
                            className="ds-input"
                            placeholder="Assunto da circular"
                            required
                        />
                        {errors.assunto && <p className="text-xs text-red-500 mt-1">{errors.assunto}</p>}
                    </div>

                    {/* Destino Tipo */}
                    <div>
                        <label className="block text-sm font-medium text-gray-700 mb-2">
                            Destino <span className="text-red-500">*</span>
                        </label>
                        <div className="flex flex-wrap gap-4">
                            {[
                                { value: 'todos', label: 'Toda Organização', icon: 'fas fa-building' },
                                { value: 'setores', label: 'Setores Específicos', icon: 'fas fa-sitemap' },
                                { value: 'usuarios', label: 'Usuários Específicos', icon: 'fas fa-users' },
                            ].map(opt => (
                                <label key={opt.value}
                                    className={`flex items-center gap-2 px-4 py-3 rounded-xl border cursor-pointer transition-all
                                        ${data.destino_tipo === opt.value
                                            ? 'border-blue-500 bg-blue-50 text-blue-700'
                                            : 'border-gray-200 hover:border-gray-300 text-gray-600'}`}>
                                    <input
                                        type="radio"
                                        name="destino_tipo"
                                        value={opt.value}
                                        checked={data.destino_tipo === opt.value}
                                        onChange={(e) => setData('destino_tipo', e.target.value)}
                                        className="hidden"
                                    />
                                    <i className={`${opt.icon} text-sm`} />
                                    <span className="text-sm font-medium">{opt.label}</span>
                                </label>
                            ))}
                        </div>
                        {errors.destino_tipo && <p className="text-xs text-red-500 mt-1">{errors.destino_tipo}</p>}
                    </div>

                    {/* Setores (condicional) */}
                    {data.destino_tipo === 'setores' && (
                        <div>
                            <label className="block text-sm font-medium text-gray-700 mb-1">
                                Setores do organograma <span className="text-red-500">*</span>
                            </label>
                            <SetoresMulti unidades={unidades} valor={(data.destino_setores || []).filter((v) => Number.isInteger(Number(v))).map(Number)}
                                onChange={(v) => setData('destino_setores', v)} />
                            <p className="mt-1 text-[11px] text-gray-500">Chega a quem atende o setor e os subsetores dele.</p>
                            {errors.destino_setores && <p className="text-xs text-red-500 mt-1">{errors.destino_setores}</p>}
                        </div>
                    )}

                    {/* Usuarios (condicional) */}
                    {data.destino_tipo === 'usuarios' && (
                        <div>
                            <label className="block text-sm font-medium text-gray-700 mb-1">
                                Destinatários <span className="text-red-500">*</span>
                            </label>
                            {data.destinatarios.length > 0 && (
                                <div className="flex flex-wrap gap-1.5 mb-2">
                                    {data.destinatarios.map(id => {
                                        const user = userList.find(u => u.id === id);
                                        return user ? (
                                            <span key={id} className="inline-flex items-center gap-1 text-xs bg-blue-50 text-blue-700 px-2 py-1 rounded-full">
                                                <i className="fas fa-user text-[9px]" />
                                                {user.name}
                                                <button type="button" onClick={() => toggleDestinatario(id)}
                                                    className="ml-0.5 hover:text-red-500">
                                                    <i className="fas fa-times text-[8px]" />
                                                </button>
                                            </span>
                                        ) : null;
                                    })}
                                </div>
                            )}
                            <div className="border border-gray-200 rounded-xl overflow-hidden">
                                <div className="px-3 py-2 border-b border-gray-100">
                                    <input
                                        type="text"
                                        value={searchUser}
                                        onChange={(e) => setSearchUser(e.target.value)}
                                        placeholder="Buscar usuário..."
                                        className="w-full text-sm outline-none"
                                    />
                                </div>
                                <div className="max-h-48 overflow-y-auto p-2 space-y-0.5">
                                    {filteredUsers.map(u => (
                                        <label key={u.id} className="flex items-center gap-2 text-sm cursor-pointer hover:bg-gray-50 rounded px-2 py-1.5">
                                            <input
                                                type="checkbox"
                                                checked={data.destinatarios.includes(u.id)}
                                                onChange={() => toggleDestinatario(u.id)}
                                                className="rounded border-gray-300 text-blue-600 w-3.5 h-3.5"
                                            />
                                            <span className="text-gray-700">{u.name}</span>
                                            <span className="text-xs text-gray-400 ml-auto">{u.email}</span>
                                        </label>
                                    ))}
                                    {filteredUsers.length === 0 && (
                                        <p className="text-xs text-gray-400 text-center py-3">Nenhum usuário encontrado</p>
                                    )}
                                </div>
                            </div>
                            {errors.destinatarios && <p className="text-xs text-red-500 mt-1">{errors.destinatarios}</p>}
                        </div>
                    )}

                    {/* Conteudo */}
                    <div>
                        <div className="flex flex-wrap items-end justify-between gap-2 mb-1">
                            <label className="block text-sm font-medium text-gray-700">
                                Conteúdo <span className="text-red-500">*</span>
                            </label>
                            <FerramentasDoTexto modulo="circular" conteudo={data.conteudo} onAplicar={(html) => setData('conteudo', html)}
                                dadosPrevia={() => dadosDoRascunho(data)} desabilitado={!! r.travadoPor} />
                        </div>
                        <RichEditor somenteLeitura={!! r.travadoPor} html={data.conteudo} onChange={(v) => setData('conteudo', v)} minHeight={260}
                            variaveis={variaveis} marcadores={['mencao', 'referencia', 'assinatura']} erro={errors.conteudo}
                            placeholder="Digite o conteúdo da circular…" />
                        {errors.conteudo && <p className="text-xs text-red-500 mt-1">{errors.conteudo}</p>}
                    </div>

                    {/* Linha com Setor Origem e Data Arquivamento */}
                    <div className="grid grid-cols-1 sm:grid-cols-2 gap-6">
                        <div>
                            <label className="block text-sm font-medium text-gray-700 mb-1">Setor de Origem</label>
                            <input
                                type="text"
                                value={data.setor_origem}
                                onChange={(e) => setData('setor_origem', e.target.value)}
                                className="ds-input"
                                placeholder="Ex: Departamento de RH"
                            />
                            {errors.setor_origem && <p className="text-xs text-red-500 mt-1">{errors.setor_origem}</p>}
                        </div>

                        <div>
                            <label className="block text-sm font-medium text-gray-700 mb-1">Arquivar automaticamente em</label>
                            <input
                                type="date"
                                value={data.data_arquivamento_auto}
                                onChange={(e) => setData('data_arquivamento_auto', e.target.value)}
                                className="ds-input w-auto"
                            />
                            {errors.data_arquivamento_auto && <p className="text-xs text-red-500 mt-1">{errors.data_arquivamento_auto}</p>}
                        </div>
                    </div>

                    {/* Prioridade e respostas (TR 4.46.4, 4.46.6, 4.46.13) */}
                    <div className="grid grid-cols-1 sm:grid-cols-2 gap-6">
                        <label className={`flex items-start gap-3 p-3 rounded-xl border-2 cursor-pointer ${data.urgente ? 'border-red-400 bg-red-50' : 'border-gray-200 hover:bg-gray-50'}`}>
                            <input type="checkbox" checked={data.urgente} onChange={(e) => setData('urgente', e.target.checked)} className="mt-0.5 rounded border-gray-300 text-red-600" />
                            <span>
                                <span className="block text-sm font-semibold text-gray-800"><i className="fas fa-bolt text-red-500 mr-1" />Urgente</span>
                                <span className="block text-[11px] text-gray-500">Aparece destacada na lista e no aviso de quem recebe.</span>
                            </span>
                        </label>
                        <div>
                            <label className="block text-sm font-medium text-gray-700 mb-1">Respostas</label>
                            <select value={data.respostas} onChange={(e) => setData('respostas', e.target.value)} className="ds-input">
                                {Object.entries(opcoes_respostas).map(([v, r]) => <option key={v} value={v}>{r}</option>)}
                            </select>
                            {errors.respostas && <p className="text-xs text-red-500 mt-1">{errors.respostas}</p>}
                        </div>
                    </div>

                    {/* Anexos */}
                    <AnexosRascunho r={r} />

                    {/* Acoes */}
                    <div className="flex justify-end gap-3 pt-4 border-t border-gray-100">
                        <Button variant="secondary" type="button" href="/circulares">Cancelar</Button>
                        <Button
                            type="submit"
                            loading={processing}
                            icon="fas fa-paper-plane"
                            disabled={
                                (data.destino_tipo === 'usuarios' && data.destinatarios.length === 0) ||
                                (data.destino_tipo === 'setores' && data.destino_setores.length === 0)
                            }
                        >
                            Enviar Circular
                        </Button>
                    </div>
                    </fieldset>
                </form>
            </div>
        </AdminLayout>
    );
}

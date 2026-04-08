/**
 * Nova Circular — GED
 *
 * Formulario de criacao de circular interna.
 */
import { Head, router, useForm } from '@inertiajs/react';
import { useState } from 'react';
import AdminLayout from '../../../Layouts/AdminLayout';
import PageHeader from '../../../Components/PageHeader';
import Button from '../../../Components/Button';

export default function CircularesCreate({ usuarios }) {
    const userList = usuarios || [];
    const [searchUser, setSearchUser] = useState('');
    const [setorInput, setSetorInput] = useState('');

    const { data, setData, post, processing, errors } = useForm({
        assunto: '',
        destino_tipo: 'todos',
        destinatarios: [],
        destino_setores: [],
        conteudo: '',
        setor_origem: '',
        data_arquivamento_auto: '',
        files: [],
    });

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

    const addSetor = () => {
        const trimmed = setorInput.trim();
        if (trimmed) {
            const novos = trimmed.split(',').map(s => s.trim()).filter(s => s && !data.destino_setores.includes(s));
            if (novos.length > 0) {
                setData('destino_setores', [...data.destino_setores, ...novos]);
            }
            setSetorInput('');
        }
    };

    const removeSetor = (setor) => {
        setData('destino_setores', data.destino_setores.filter(s => s !== setor));
    };

    const submit = (e) => {
        e.preventDefault();

        const formData = new FormData();
        formData.append('assunto', data.assunto);
        formData.append('conteudo', data.conteudo);
        formData.append('destino_tipo', data.destino_tipo);
        formData.append('setor_origem', data.setor_origem);
        formData.append('data_arquivamento_auto', data.data_arquivamento_auto);

        if (data.destino_tipo === 'usuarios') {
            data.destinatarios.forEach((id, i) => {
                formData.append(`destinatarios[${i}]`, id);
            });
        }

        if (data.destino_tipo === 'setores') {
            data.destino_setores.forEach((setor, i) => {
                formData.append(`destino_setores[${i}]`, setor);
            });
        }

        if (data.files.length > 0) {
            Array.from(data.files).forEach((file, i) => {
                formData.append(`files[${i}]`, file);
            });
        }

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
                                { value: 'todos', label: 'Toda Organizacao', icon: 'fas fa-building' },
                                { value: 'setores', label: 'Setores Especificos', icon: 'fas fa-sitemap' },
                                { value: 'usuarios', label: 'Usuarios Especificos', icon: 'fas fa-users' },
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
                                Setores <span className="text-red-500">*</span>
                            </label>
                            {data.destino_setores.length > 0 && (
                                <div className="flex flex-wrap gap-1.5 mb-2">
                                    {data.destino_setores.map(setor => (
                                        <span key={setor} className="inline-flex items-center gap-1 text-xs bg-blue-50 text-blue-700 px-2 py-1 rounded-full">
                                            <i className="fas fa-sitemap text-[9px]" />
                                            {setor}
                                            <button type="button" onClick={() => removeSetor(setor)}
                                                className="ml-0.5 hover:text-red-500">
                                                <i className="fas fa-times text-[8px]" />
                                            </button>
                                        </span>
                                    ))}
                                </div>
                            )}
                            <div className="flex gap-2">
                                <input
                                    type="text"
                                    value={setorInput}
                                    onChange={(e) => setSetorInput(e.target.value)}
                                    onKeyDown={(e) => { if (e.key === 'Enter') { e.preventDefault(); addSetor(); } }}
                                    className="ds-input flex-1"
                                    placeholder="Digite nomes de setores separados por virgula"
                                />
                                <Button type="button" variant="secondary" onClick={addSetor}>Adicionar</Button>
                            </div>
                            {errors.destino_setores && <p className="text-xs text-red-500 mt-1">{errors.destino_setores}</p>}
                        </div>
                    )}

                    {/* Usuarios (condicional) */}
                    {data.destino_tipo === 'usuarios' && (
                        <div>
                            <label className="block text-sm font-medium text-gray-700 mb-1">
                                Destinatarios <span className="text-red-500">*</span>
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
                                        placeholder="Buscar usuario..."
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
                                        <p className="text-xs text-gray-400 text-center py-3">Nenhum usuario encontrado</p>
                                    )}
                                </div>
                            </div>
                            {errors.destinatarios && <p className="text-xs text-red-500 mt-1">{errors.destinatarios}</p>}
                        </div>
                    )}

                    {/* Conteudo */}
                    <div>
                        <label className="block text-sm font-medium text-gray-700 mb-1">
                            Conteudo <span className="text-red-500">*</span>
                        </label>
                        <textarea
                            value={data.conteudo}
                            onChange={(e) => setData('conteudo', e.target.value)}
                            className="ds-input !h-auto"
                            rows={10}
                            placeholder="Digite o conteudo da circular..."
                            required
                        />
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

                    {/* Anexos */}
                    <div>
                        <label className="block text-sm font-medium text-gray-700 mb-1">
                            <i className="fas fa-paperclip text-xs text-gray-400 mr-1" />
                            Anexos
                        </label>
                        <input
                            type="file"
                            multiple
                            onChange={(e) => setData('files', e.target.files)}
                            className="block w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-sm file:font-medium file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100"
                        />
                        {errors.files && <p className="text-xs text-red-500 mt-1">{errors.files}</p>}
                    </div>

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
                </form>
            </div>
        </AdminLayout>
    );
}

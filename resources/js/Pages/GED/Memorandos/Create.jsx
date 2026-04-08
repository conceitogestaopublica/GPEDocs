/**
 * Novo Memorando — GED
 *
 * Formulario de criacao de memorando interno.
 */
import { Head, router, useForm } from '@inertiajs/react';
import { useState } from 'react';
import AdminLayout from '../../../Layouts/AdminLayout';
import PageHeader from '../../../Components/PageHeader';
import Button from '../../../Components/Button';

export default function MemorandosCreate({ usuarios }) {
    const userList = usuarios || [];
    const [searchUser, setSearchUser] = useState('');

    const { data, setData, post, processing, errors } = useForm({
        assunto: '',
        destinatarios: [],
        conteudo: '',
        confidencial: false,
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

    const submit = (e) => {
        e.preventDefault();

        const formData = new FormData();
        formData.append('assunto', data.assunto);
        formData.append('conteudo', data.conteudo);
        formData.append('confidencial', data.confidencial ? '1' : '0');
        formData.append('setor_origem', data.setor_origem);
        formData.append('data_arquivamento_auto', data.data_arquivamento_auto);

        data.destinatarios.forEach((id, i) => {
            formData.append(`destinatarios[${i}]`, id);
        });

        if (data.files.length > 0) {
            Array.from(data.files).forEach((file, i) => {
                formData.append(`files[${i}]`, file);
            });
        }

        router.post('/memorandos', formData, {
            forceFormData: true,
        });
    };

    return (
        <AdminLayout>
            <Head title="Novo Memorando" />
            <PageHeader
                title="Novo Memorando"
                subtitle="Criar e enviar um memorando interno"
            >
                <Button variant="secondary" icon="fas fa-arrow-left" href="/memorandos">Voltar</Button>
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
                            placeholder="Assunto do memorando"
                            required
                        />
                        {errors.assunto && <p className="text-xs text-red-500 mt-1">{errors.assunto}</p>}
                    </div>

                    {/* Destinatarios */}
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
                            placeholder="Digite o conteudo do memorando..."
                            required
                        />
                        {errors.conteudo && <p className="text-xs text-red-500 mt-1">{errors.conteudo}</p>}
                    </div>

                    {/* Linha com Setor e Confidencial */}
                    <div className="grid grid-cols-1 sm:grid-cols-2 gap-6">
                        {/* Setor Origem */}
                        <div>
                            <label className="block text-sm font-medium text-gray-700 mb-1">Setor de Origem</label>
                            <input
                                type="text"
                                value={data.setor_origem}
                                onChange={(e) => setData('setor_origem', e.target.value)}
                                className="ds-input"
                                placeholder="Ex: Departamento de TI"
                            />
                            {errors.setor_origem && <p className="text-xs text-red-500 mt-1">{errors.setor_origem}</p>}
                        </div>

                        {/* Data Arquivamento Automatico */}
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

                    {/* Confidencial */}
                    <div>
                        <label className="flex items-center gap-2 cursor-pointer">
                            <input
                                type="checkbox"
                                checked={data.confidencial}
                                onChange={(e) => setData('confidencial', e.target.checked)}
                                className="rounded border-gray-300 text-blue-600 w-4 h-4"
                            />
                            <span className="text-sm font-medium text-gray-700">
                                <i className="fas fa-lock text-xs text-gray-400 mr-1" />
                                Memorando Confidencial
                            </span>
                        </label>
                        <p className="text-xs text-gray-400 mt-1 ml-6">
                            Memorandos confidenciais so podem ser visualizados pelos destinatarios.
                        </p>
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
                        <Button variant="secondary" type="button" href="/memorandos">Cancelar</Button>
                        <Button
                            type="submit"
                            loading={processing}
                            icon="fas fa-paper-plane"
                            disabled={data.destinatarios.length === 0}
                        >
                            Enviar Memorando
                        </Button>
                    </div>
                </form>
            </div>
        </AdminLayout>
    );
}

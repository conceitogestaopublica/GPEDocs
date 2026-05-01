/**
 * Novo Memorando — GED
 *
 * Formulario de criacao de memorando interno.
 */
import { Head, router, useForm } from '@inertiajs/react';
import { useMemo, useState } from 'react';
import AdminLayout from '../../../Layouts/AdminLayout';
import PageHeader from '../../../Components/PageHeader';
import Button from '../../../Components/Button';

export default function MemorandosCreate({ usuarios, unidades = [] }) {
    const userList = usuarios || [];
    const unidadeList = unidades || [];
    const [searchUser, setSearchUser] = useState('');

    const { data, setData, processing, errors } = useForm({
        assunto: '',
        tipo_destino: 'setor', // todos_setores | setor | usuario
        unidade_id: '',
        destinatarios: [],
        conteudo: '',
        confidencial: false,
        setor_origem: '',
        data_arquivamento_auto: '',
        files: [],
    });

    // Pra modo "usuario": filtra usuarios pelo setor escolhido (se tiver) + busca por nome/email
    const filteredUsers = useMemo(() => {
        let lista = userList;
        if (data.tipo_destino === 'usuario' && data.unidade_id) {
            lista = lista.filter(u => Number(u.unidade_id) === Number(data.unidade_id));
        }
        if (searchUser.trim()) {
            const t = searchUser.toLowerCase();
            lista = lista.filter(u => u.name.toLowerCase().includes(t) || u.email.toLowerCase().includes(t));
        }
        return lista;
    }, [userList, data.tipo_destino, data.unidade_id, searchUser]);

    const toggleDestinatario = (id) => {
        const list = data.destinatarios.includes(id)
            ? data.destinatarios.filter(x => x !== id)
            : [...data.destinatarios, id];
        setData('destinatarios', list);
    };

    const trocarModo = (modo) => {
        setData(d => ({ ...d, tipo_destino: modo, unidade_id: '', destinatarios: [] }));
        setSearchUser('');
    };

    const submit = (e) => {
        e.preventDefault();

        const formData = new FormData();
        formData.append('assunto', data.assunto);
        formData.append('conteudo', data.conteudo);
        formData.append('confidencial', data.confidencial ? '1' : '0');
        formData.append('setor_origem', data.setor_origem);
        formData.append('data_arquivamento_auto', data.data_arquivamento_auto);
        formData.append('tipo_destino', data.tipo_destino);

        if (data.tipo_destino === 'setor' || data.tipo_destino === 'usuario') {
            if (data.unidade_id) formData.append('unidade_id', data.unidade_id);
        }
        if (data.tipo_destino === 'usuario') {
            data.destinatarios.forEach((id, i) => {
                formData.append(`destinatarios[${i}]`, id);
            });
        }

        if (data.files.length > 0) {
            Array.from(data.files).forEach((file, i) => {
                formData.append(`files[${i}]`, file);
            });
        }

        router.post('/memorandos', formData, {
            forceFormData: true,
        });
    };

    // Hierarquia visual: filhos vem logo abaixo do pai (depth-first)
    const unidadesOrdenadas = useMemo(() => {
        const filhosPor = new Map();
        unidadeList.forEach(u => {
            const pid = u.parent_id ?? null;
            if (! filhosPor.has(pid)) filhosPor.set(pid, []);
            filhosPor.get(pid).push(u);
        });
        for (const [, lista] of filhosPor) {
            lista.sort((a, b) => a.nome.localeCompare(b.nome));
        }
        const resultado = [];
        const visitar = (parentId) => {
            const filhos = filhosPor.get(parentId) || [];
            for (const u of filhos) {
                resultado.push(u);
                visitar(u.id);
            }
        };
        visitar(null);
        return resultado;
    }, [unidadeList]);

    const podeEnviar =
        data.tipo_destino === 'todos_setores' ||
        (data.tipo_destino === 'setor' && data.unidade_id) ||
        (data.tipo_destino === 'usuario' && data.destinatarios.length > 0);

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

                    {/* Para quem enviar — 3 modos */}
                    <div>
                        <label className="block text-sm font-medium text-gray-700 mb-2">
                            Para quem enviar <span className="text-red-500">*</span>
                        </label>
                        <div className="grid grid-cols-1 md:grid-cols-3 gap-2 mb-3">
                            {[
                                { v: 'todos_setores', icone: 'fa-broadcast-tower', cor: 'rose',    titulo: 'Todos os Setores',     desc: 'Toda a UG ve na Caixa Setor' },
                                { v: 'setor',         icone: 'fa-users',           cor: 'indigo',  titulo: 'Apenas um Setor',      desc: 'Setor especifico (Caixa Setor)' },
                                { v: 'usuario',       icone: 'fa-user',            cor: 'blue',    titulo: 'Setor + Usuario',      desc: 'Pessoa especifica (Caixa Pessoal)' },
                            ].map(op => {
                                const ativo = data.tipo_destino === op.v;
                                const corMap = { rose: 'border-rose-500 bg-rose-50', indigo: 'border-indigo-500 bg-indigo-50', blue: 'border-blue-500 bg-blue-50' };
                                const iconeMap = { rose: 'text-rose-600', indigo: 'text-indigo-600', blue: 'text-blue-600' };
                                return (
                                    <button key={op.v} type="button" onClick={() => trocarModo(op.v)}
                                        className={`text-left p-3 rounded-xl border-2 transition-colors ${ativo ? corMap[op.cor] : 'border-gray-200 hover:bg-gray-50'}`}>
                                        <div className="flex items-center gap-2">
                                            <i className={`fas ${op.icone} ${ativo ? iconeMap[op.cor] : 'text-gray-400'}`} />
                                            <div>
                                                <p className="text-sm font-semibold text-gray-800">{op.titulo}</p>
                                                <p className="text-[10px] text-gray-500 leading-tight">{op.desc}</p>
                                            </div>
                                        </div>
                                    </button>
                                );
                            })}
                        </div>
                        {errors.tipo_destino && <p className="text-xs text-red-500 mt-1">{errors.tipo_destino}</p>}

                        {/* Modo todos_setores: sem campos extras */}
                        {data.tipo_destino === 'todos_setores' && (
                            <div className="bg-rose-50 border border-rose-200 rounded-xl p-3 text-xs text-rose-800">
                                <i className="fas fa-info-circle mr-1" />
                                O memorando sera enviado para <strong>todos os setores</strong> da UG ativa. Cada setor vera o documento na sua Caixa Entrada Setor.
                            </div>
                        )}

                        {/* Modo setor: dropdown de unidade */}
                        {data.tipo_destino === 'setor' && (
                            <div>
                                <label className="block text-xs font-medium text-gray-600 mb-1">Setor de destino</label>
                                <select value={data.unidade_id}
                                    onChange={(e) => setData('unidade_id', e.target.value)}
                                    className="ds-input">
                                    <option value="">— Selecione o setor —</option>
                                    {unidadesOrdenadas.map(u => {
                                        const indent = '— '.repeat(Math.max(0, u.nivel - 1));
                                        return (
                                            <option key={u.id} value={u.id}>{indent}[N{u.nivel}] {u.nome}</option>
                                        );
                                    })}
                                </select>
                                {errors.unidade_id && <p className="text-xs text-red-500 mt-1">{errors.unidade_id}</p>}
                            </div>
                        )}

                        {/* Modo usuario: setor (filtro) + lista de pessoas daquele setor */}
                        {data.tipo_destino === 'usuario' && (
                            <div className="space-y-3">
                                <div>
                                    <label className="block text-xs font-medium text-gray-600 mb-1">
                                        Filtrar por setor <span className="text-gray-400">(opcional — restringe a lista de pessoas)</span>
                                    </label>
                                    <select value={data.unidade_id}
                                        onChange={(e) => setData(d => ({ ...d, unidade_id: e.target.value, destinatarios: [] }))}
                                        className="ds-input">
                                        <option value="">— Todos os setores —</option>
                                        {unidadesOrdenadas.map(u => {
                                            const indent = '— '.repeat(Math.max(0, u.nivel - 1));
                                            return (
                                                <option key={u.id} value={u.id}>{indent}[N{u.nivel}] {u.nome}</option>
                                            );
                                        })}
                                    </select>
                                </div>

                                {data.destinatarios.length > 0 && (
                                    <div className="flex flex-wrap gap-1.5">
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
                                        <input type="text" value={searchUser}
                                            onChange={(e) => setSearchUser(e.target.value)}
                                            placeholder="Buscar pessoa por nome ou e-mail..."
                                            className="w-full text-sm outline-none" />
                                    </div>
                                    <div className="max-h-48 overflow-y-auto p-2 space-y-0.5">
                                        {filteredUsers.map(u => (
                                            <label key={u.id} className="flex items-center gap-2 text-sm cursor-pointer hover:bg-gray-50 rounded px-2 py-1.5">
                                                <input type="checkbox" checked={data.destinatarios.includes(u.id)}
                                                    onChange={() => toggleDestinatario(u.id)}
                                                    className="rounded border-gray-300 text-blue-600 w-3.5 h-3.5" />
                                                <span className="text-gray-700">{u.name}</span>
                                                <span className="text-xs text-gray-400 ml-auto">{u.email}</span>
                                            </label>
                                        ))}
                                        {filteredUsers.length === 0 && (
                                            <p className="text-xs text-gray-400 text-center py-3">Nenhum usuario encontrado{data.unidade_id ? ' nesse setor' : ''}</p>
                                        )}
                                    </div>
                                </div>
                                {errors.destinatarios && <p className="text-xs text-red-500 mt-1">{errors.destinatarios}</p>}
                            </div>
                        )}
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
                            disabled={! podeEnviar}
                        >
                            Enviar Memorando
                        </Button>
                    </div>
                </form>
            </div>
        </AdminLayout>
    );
}

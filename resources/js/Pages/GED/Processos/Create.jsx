/**
 * Abertura de Processo — GED
 *
 * Formulario com campos dinamicos baseados no tipo de processo selecionado.
 */
import { Head, router, useForm } from '@inertiajs/react';
import { useMemo, useState } from 'react';
import AdminLayout from '../../../Layouts/AdminLayout';
import PageHeader from '../../../Components/PageHeader';
import Button from '../../../Components/Button';
import Card from '../../../Components/Card';

export default function Create({ tipos_processo, unidades = [], usuarios = [] }) {
    const tipos = tipos_processo || [];
    const [files, setFiles] = useState([]);

    const { data, setData, post, processing, errors } = useForm({
        tipo_processo_id: '',
        assunto: '',
        descricao: '',
        prioridade: 'normal',
        dados_formulario: {},
        requerente_nome: '',
        requerente_cpf: '',
        requerente_email: '',
        requerente_telefone: '',
        setor_destino_inicial: '',
        destinatario_inicial: '',
    });

    // Combobox setor
    const [setorSearch, setSetorSearch] = useState('');
    const [setorOpen, setSetorOpen] = useState(false);
    const unidadesTree = useMemo(() => {
        const filhosPor = new Map();
        (unidades || []).forEach(u => {
            const pid = u.parent_id ?? null;
            if (! filhosPor.has(pid)) filhosPor.set(pid, []);
            filhosPor.get(pid).push(u);
        });
        for (const [, l] of filhosPor) l.sort((a, b) => a.nome.localeCompare(b.nome));
        const out = [];
        const visit = (pid) => {
            for (const u of (filhosPor.get(pid) || [])) {
                out.push(u);
                visit(u.id);
            }
        };
        visit(null);
        return out;
    }, [unidades]);
    const unidadesFiltradas = useMemo(() => {
        if (! setorSearch.trim()) return unidadesTree;
        const t = setorSearch.toLowerCase();
        return unidadesTree.filter(u => u.nome.toLowerCase().includes(t));
    }, [unidadesTree, setorSearch]);
    const setorSelecionado = unidadesTree.find(u => String(u.id) === String(data.setor_destino_inicial));
    const usuariosFiltrados = useMemo(() => {
        if (! data.setor_destino_inicial) return usuarios;
        return usuarios.filter(u => Number(u.unidade_id) === Number(data.setor_destino_inicial));
    }, [usuarios, data.setor_destino_inicial]);

    const tipoSelecionado = tipos.find(t => String(t.id) === String(data.tipo_processo_id));
    const schemaCampos = tipoSelecionado?.schema_formulario || [];

    const handleTipoChange = (tipoId) => {
        setData(prev => ({ ...prev, tipo_processo_id: tipoId, dados_formulario: {} }));
    };

    const setDadoFormulario = (campo, valor) => {
        setData('dados_formulario', { ...data.dados_formulario, [campo]: valor });
    };

    const handleFileInput = (e) => {
        const newFiles = Array.from(e.target.files);
        setFiles(prev => [...prev, ...newFiles]);
        e.target.value = '';
    };

    const removeFile = (index) => {
        setFiles(prev => prev.filter((_, i) => i !== index));
    };

    const submit = (e) => {
        e.preventDefault();
        const formData = new FormData();

        formData.append('tipo_processo_id', data.tipo_processo_id);
        formData.append('assunto', data.assunto);
        formData.append('descricao', data.descricao);
        formData.append('prioridade', data.prioridade);

        // Dados dinamicos do formulario
        Object.entries(data.dados_formulario).forEach(([chave, valor]) => {
            if (valor !== '' && valor !== null && valor !== undefined) {
                formData.append(`dados_formulario[${chave}]`, valor);
            }
        });

        // Requerente
        if (data.requerente_nome) formData.append('requerente_nome', data.requerente_nome);
        if (data.requerente_cpf) formData.append('requerente_cpf', data.requerente_cpf);
        if (data.requerente_email) formData.append('requerente_email', data.requerente_email);
        if (data.requerente_telefone) formData.append('requerente_telefone', data.requerente_telefone);

        // Destino inicial
        if (data.setor_destino_inicial) formData.append('setor_destino_inicial', data.setor_destino_inicial);
        if (data.destinatario_inicial) formData.append('destinatario_inicial', data.destinatario_inicial);

        // Arquivos
        files.forEach((file, i) => formData.append(`files[${i}]`, file));

        router.post('/processos', formData, { forceFormData: true });
    };

    return (
        <AdminLayout>
            <Head title="Abrir Processo" />

            <PageHeader title="Abrir Processo" subtitle="Preencha os dados para abrir um novo processo">
                <Button variant="secondary" href="/processos" icon="fas fa-arrow-left">
                    Voltar
                </Button>
            </PageHeader>

            <form onSubmit={submit}>
                <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
                    {/* Coluna esquerda - Formulario */}
                    <div className="lg:col-span-2 space-y-6">
                        {/* Dados principais */}
                        <Card title="Dados do Processo">
                            <div className="space-y-4">
                                <div>
                                    <label className="block text-sm font-medium text-gray-700 mb-1">
                                        Tipo de Processo <span className="text-red-500">*</span>
                                    </label>
                                    <select
                                        value={data.tipo_processo_id}
                                        onChange={(e) => handleTipoChange(e.target.value)}
                                        className="ds-input"
                                    >
                                        <option value="">Selecionar tipo...</option>
                                        {tipos.map(t => (
                                            <option key={t.id} value={t.id}>{t.nome} {t.sigla ? `(${t.sigla})` : ''}</option>
                                        ))}
                                    </select>
                                    {errors.tipo_processo_id && <p className="mt-1 text-xs text-red-600">{errors.tipo_processo_id}</p>}
                                </div>

                                <div>
                                    <label className="block text-sm font-medium text-gray-700 mb-1">
                                        Assunto <span className="text-red-500">*</span>
                                    </label>
                                    <input
                                        type="text"
                                        value={data.assunto}
                                        onChange={(e) => setData('assunto', e.target.value)}
                                        className="ds-input"
                                        placeholder="Descricao breve do processo"
                                    />
                                    {errors.assunto && <p className="mt-1 text-xs text-red-600">{errors.assunto}</p>}
                                </div>

                                <div>
                                    <label className="block text-sm font-medium text-gray-700 mb-1">Descricao</label>
                                    <textarea
                                        value={data.descricao}
                                        onChange={(e) => setData('descricao', e.target.value)}
                                        className="ds-input !h-auto"
                                        rows={4}
                                        placeholder="Detalhes adicionais sobre o processo..."
                                    />
                                    {errors.descricao && <p className="mt-1 text-xs text-red-600">{errors.descricao}</p>}
                                </div>

                                <div>
                                    <label className="block text-sm font-medium text-gray-700 mb-1">Prioridade</label>
                                    <select
                                        value={data.prioridade}
                                        onChange={(e) => setData('prioridade', e.target.value)}
                                        className="ds-input"
                                    >
                                        <option value="baixa">Baixa</option>
                                        <option value="normal">Normal</option>
                                        <option value="alta">Alta</option>
                                        <option value="urgente">Urgente</option>
                                    </select>
                                </div>
                            </div>
                        </Card>

                        {/* Campos dinamicos do tipo de processo */}
                        {schemaCampos.length > 0 && (
                            <Card title={`Formulario - ${tipoSelecionado.nome}`}>
                                <div className="space-y-4">
                                    {schemaCampos.map((campo) => (
                                        <div key={campo.campo}>
                                            <label className="block text-sm font-medium text-gray-700 mb-1">
                                                {campo.label}
                                                {campo.obrigatorio && <span className="text-red-500 ml-0.5">*</span>}
                                            </label>
                                            {renderDynamicField(campo, data.dados_formulario[campo.campo] || '', (val) => setDadoFormulario(campo.campo, val))}
                                            {errors[`dados_formulario.${campo.campo}`] && (
                                                <p className="mt-1 text-xs text-red-600">{errors[`dados_formulario.${campo.campo}`]}</p>
                                            )}
                                        </div>
                                    ))}
                                </div>
                            </Card>
                        )}

                        {/* Requerente */}
                        <Card title="Requerente" subtitle="Informacoes do solicitante (opcional)">
                            <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div>
                                    <label className="block text-sm font-medium text-gray-700 mb-1">Nome</label>
                                    <input
                                        type="text"
                                        value={data.requerente_nome}
                                        onChange={(e) => setData('requerente_nome', e.target.value)}
                                        className="ds-input"
                                        placeholder="Nome completo"
                                    />
                                </div>
                                <div>
                                    <label className="block text-sm font-medium text-gray-700 mb-1">CPF</label>
                                    <input
                                        type="text"
                                        value={data.requerente_cpf}
                                        onChange={(e) => setData('requerente_cpf', e.target.value)}
                                        className="ds-input"
                                        placeholder="000.000.000-00"
                                    />
                                </div>
                                <div>
                                    <label className="block text-sm font-medium text-gray-700 mb-1">E-mail</label>
                                    <input
                                        type="email"
                                        value={data.requerente_email}
                                        onChange={(e) => setData('requerente_email', e.target.value)}
                                        className="ds-input"
                                        placeholder="email@exemplo.com"
                                    />
                                </div>
                                <div>
                                    <label className="block text-sm font-medium text-gray-700 mb-1">Telefone</label>
                                    <input
                                        type="text"
                                        value={data.requerente_telefone}
                                        onChange={(e) => setData('requerente_telefone', e.target.value)}
                                        className="ds-input"
                                        placeholder="(00) 00000-0000"
                                    />
                                </div>
                            </div>
                        </Card>

                        {/* Destino inicial — para onde o processo vai */}
                        <Card title="Para qual setor enviar" subtitle="Setor que vai receber o processo na Caixa de Entrada (obrigatorio)" className="overflow-visible">
                            <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div className="relative">
                                    <label className="block text-sm font-medium text-gray-700 mb-1">
                                        Setor de Destino <span className="text-red-500">*</span>
                                    </label>
                                    <div className="relative">
                                        <input
                                            type="text"
                                            value={setorOpen ? setorSearch : (setorSelecionado?.nome || '')}
                                            onChange={(e) => { setSetorSearch(e.target.value); setSetorOpen(true); }}
                                            onFocus={() => { setSetorOpen(true); setSetorSearch(''); }}
                                            placeholder="Digite para buscar setor..."
                                            className="ds-input pr-9"
                                        />
                                        {data.setor_destino_inicial && (
                                            <button type="button"
                                                onClick={() => { setData(d => ({ ...d, setor_destino_inicial: '', destinatario_inicial: '' })); setSetorSearch(''); }}
                                                className="absolute right-2 top-1/2 -translate-y-1/2 text-gray-400 hover:text-red-500">
                                                <i className="fas fa-times text-xs" />
                                            </button>
                                        )}
                                    </div>
                                    {setorOpen && (
                                        <>
                                            <div className="fixed inset-0 z-30" onClick={() => setSetorOpen(false)} />
                                            <div className="absolute z-40 mt-1 w-full bg-white border border-gray-200 rounded-xl shadow-lg max-h-72 overflow-auto py-1">
                                                {unidadesFiltradas.length === 0 ? (
                                                    <p className="p-3 text-xs text-gray-400 text-center">Nenhum setor encontrado</p>
                                                ) : unidadesFiltradas.map(u => {
                                                    const isTopo = u.nivel === 1;
                                                    const indent = (u.nivel - 1) * 16;
                                                    return (
                                                        <button key={u.id} type="button"
                                                            onClick={() => { setData(d => ({ ...d, setor_destino_inicial: u.id, destinatario_inicial: '' })); setSetorOpen(false); setSetorSearch(''); }}
                                                            style={{ paddingLeft: `${12 + indent}px` }}
                                                            className={`w-full text-left pr-3 py-1.5 text-sm transition-colors ${
                                                                isTopo
                                                                    ? 'bg-blue-50 text-blue-800 font-bold border-l-4 border-blue-500 hover:bg-blue-100'
                                                                    : 'text-gray-700 hover:bg-gray-100 border-l-4 border-transparent'
                                                            }`}>
                                                            {! isTopo && <i className="fas fa-angle-right text-gray-300 text-[10px] mr-1.5" />}
                                                            {u.nome}
                                                        </button>
                                                    );
                                                })}
                                            </div>
                                        </>
                                    )}
                                    {errors.setor_destino_inicial && (
                                        <p className="mt-1 text-xs text-red-600">{errors.setor_destino_inicial}</p>
                                    )}
                                </div>

                                <div>
                                    <label className="block text-sm font-medium text-gray-700 mb-1">
                                        Pessoa especifica <span className="text-gray-400 text-xs">(opcional)</span>
                                    </label>
                                    <select
                                        value={data.destinatario_inicial}
                                        onChange={(e) => setData('destinatario_inicial', e.target.value)}
                                        className="ds-input"
                                    >
                                        <option value="">— Qualquer pessoa do setor —</option>
                                        {usuariosFiltrados.map(u => (
                                            <option key={u.id} value={u.id}>{u.name}</option>
                                        ))}
                                    </select>
                                    <p className="mt-1 text-[10px] text-gray-400">
                                        Se vazio, qualquer um do setor podera receber via Caixa Setor.
                                    </p>
                                </div>
                            </div>
                        </Card>

                        {/* Anexos */}
                        <Card title="Anexos">
                            <div
                                className="border-2 border-dashed rounded-xl p-6 text-center transition-colors cursor-pointer border-gray-200 bg-gray-50 hover:border-blue-300"
                                onClick={() => document.getElementById('processFileInput').click()}
                            >
                                <i className="fas fa-paperclip text-3xl mb-2 block text-gray-300" />
                                <p className="text-sm font-medium text-gray-600">Clique para anexar arquivos</p>
                                <p className="text-xs text-gray-400 mt-1">PDF, DOC, DOCX, XLS, XLSX, JPG, PNG</p>
                                <input
                                    id="processFileInput"
                                    type="file"
                                    multiple
                                    onChange={handleFileInput}
                                    className="hidden"
                                    accept=".pdf,.doc,.docx,.xls,.xlsx,.jpg,.jpeg,.png"
                                />
                            </div>
                            {files.length > 0 && (
                                <div className="mt-4 space-y-2">
                                    {files.map((file, idx) => (
                                        <div key={idx} className="flex items-center justify-between bg-gray-50 rounded-lg px-4 py-3">
                                            <div className="flex items-center gap-3">
                                                <i className={`${getFileIcon(file.type)} text-lg`} />
                                                <div>
                                                    <p className="text-sm font-medium text-gray-700">{file.name}</p>
                                                    <p className="text-xs text-gray-400">{formatBytes(file.size)}</p>
                                                </div>
                                            </div>
                                            <button type="button" onClick={() => removeFile(idx)}
                                                className="text-red-400 hover:text-red-600 transition-colors">
                                                <i className="fas fa-times" />
                                            </button>
                                        </div>
                                    ))}
                                </div>
                            )}
                            {errors.files && <p className="mt-2 text-xs text-red-600">{errors.files}</p>}
                        </Card>
                    </div>

                    {/* Coluna direita - Info/Ajuda */}
                    <div className="space-y-6">
                        {/* Tipo selecionado info */}
                        {tipoSelecionado ? (
                            <Card title="Tipo de Processo">
                                <div className="space-y-3">
                                    <div>
                                        <p className="text-lg font-semibold text-gray-800">{tipoSelecionado.nome}</p>
                                        {tipoSelecionado.sigla && (
                                            <span className="inline-block mt-1 px-2 py-0.5 bg-blue-100 text-blue-700 text-xs rounded-full font-medium">
                                                {tipoSelecionado.sigla}
                                            </span>
                                        )}
                                    </div>
                                    {tipoSelecionado.descricao && (
                                        <p className="text-sm text-gray-500">{tipoSelecionado.descricao}</p>
                                    )}
                                    {tipoSelecionado.sla_padrao_horas && (
                                        <div className="flex items-center gap-2 text-sm text-gray-500">
                                            <i className="fas fa-clock text-gray-400" />
                                            <span>SLA: {tipoSelecionado.sla_padrao_horas}h</span>
                                        </div>
                                    )}
                                    {tipoSelecionado.etapas && tipoSelecionado.etapas.length > 0 && (
                                        <div>
                                            <p className="text-xs font-semibold text-gray-500 uppercase mb-2">Etapas do fluxo</p>
                                            <div className="space-y-1.5">
                                                {tipoSelecionado.etapas.map((etapa, idx) => (
                                                    <div key={idx} className="flex items-center gap-2 text-xs">
                                                        <span className="w-5 h-5 bg-blue-100 text-blue-600 rounded-full flex items-center justify-center font-bold text-[10px]">
                                                            {idx + 1}
                                                        </span>
                                                        <span className="text-gray-600">{etapa.nome || etapa}</span>
                                                    </div>
                                                ))}
                                            </div>
                                        </div>
                                    )}
                                </div>
                            </Card>
                        ) : (
                            <Card>
                                <div className="text-center py-4 text-gray-400">
                                    <i className="fas fa-info-circle text-2xl mb-2 block" />
                                    <p className="text-sm font-medium">Selecione um tipo</p>
                                    <p className="text-xs mt-1">As informacoes do tipo de processo serao exibidas aqui</p>
                                </div>
                            </Card>
                        )}

                        {/* Ajuda */}
                        <Card title="Ajuda">
                            <div className="space-y-3 text-sm text-gray-500">
                                <div className="flex items-start gap-2">
                                    <i className="fas fa-lightbulb text-yellow-400 mt-0.5" />
                                    <p>Selecione o tipo de processo para que os campos especificos sejam exibidos automaticamente.</p>
                                </div>
                                <div className="flex items-start gap-2">
                                    <i className="fas fa-lightbulb text-yellow-400 mt-0.5" />
                                    <p>O numero de protocolo sera gerado automaticamente apos a abertura.</p>
                                </div>
                                <div className="flex items-start gap-2">
                                    <i className="fas fa-lightbulb text-yellow-400 mt-0.5" />
                                    <p>Processos urgentes terao prioridade na fila de tramitacao.</p>
                                </div>
                            </div>
                        </Card>

                        {/* Submit */}
                        <Button
                            type="submit"
                            loading={processing}
                            icon="fas fa-paper-plane"
                            className="w-full justify-center"
                        >
                            Abrir Processo
                        </Button>
                    </div>
                </div>
            </form>
        </AdminLayout>
    );
}

function renderDynamicField(campo, value, onChange) {
    switch (campo.tipo) {
        case 'text':
            return (
                <input
                    type="text"
                    value={value}
                    onChange={(e) => onChange(e.target.value)}
                    className="ds-input"
                    placeholder={campo.label}
                />
            );
        case 'number':
            return (
                <input
                    type="number"
                    value={value}
                    onChange={(e) => onChange(e.target.value)}
                    className="ds-input"
                    placeholder={campo.label}
                    step="any"
                />
            );
        case 'money':
            return (
                <input
                    type="number"
                    value={value}
                    onChange={(e) => onChange(e.target.value)}
                    className="ds-input"
                    placeholder="0,00"
                    step="0.01"
                    min="0"
                />
            );
        case 'date':
            return (
                <input
                    type="date"
                    value={value}
                    onChange={(e) => onChange(e.target.value)}
                    className="ds-input"
                />
            );
        case 'textarea':
            return (
                <textarea
                    value={value}
                    onChange={(e) => onChange(e.target.value)}
                    className="ds-input !h-auto"
                    rows={3}
                    placeholder={campo.label}
                />
            );
        case 'select':
            return (
                <select
                    value={value}
                    onChange={(e) => onChange(e.target.value)}
                    className="ds-input"
                >
                    <option value="">Selecionar...</option>
                    {(campo.opcoes || '').split(',').filter(Boolean).map(op => (
                        <option key={op.trim()} value={op.trim()}>{op.trim()}</option>
                    ))}
                </select>
            );
        default:
            return (
                <input
                    type="text"
                    value={value}
                    onChange={(e) => onChange(e.target.value)}
                    className="ds-input"
                    placeholder={campo.label}
                />
            );
    }
}

function getFileIcon(mime) {
    if (!mime) return 'fas fa-file text-gray-400';
    if (mime.includes('pdf')) return 'fas fa-file-pdf text-red-400';
    if (mime.includes('image')) return 'fas fa-file-image text-purple-400';
    if (mime.includes('word') || mime.includes('document')) return 'fas fa-file-word text-blue-400';
    if (mime.includes('sheet') || mime.includes('excel')) return 'fas fa-file-excel text-green-400';
    return 'fas fa-file text-gray-400';
}

function formatBytes(bytes) {
    if (!bytes) return '0 B';
    const k = 1024;
    const sizes = ['B', 'KB', 'MB', 'GB'];
    const i = Math.floor(Math.log(bytes) / Math.log(k));
    return parseFloat((bytes / Math.pow(k, i)).toFixed(1)) + ' ' + sizes[i];
}

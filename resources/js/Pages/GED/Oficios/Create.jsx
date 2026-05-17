/**
 * Novo Oficio Eletronico — GED
 *
 * Formulario de criacao de oficio para destinatario externo.
 */
import { Head, router, useForm } from '@inertiajs/react';
import { useState } from 'react';
import AdminLayout from '../../../Layouts/AdminLayout';
import PageHeader from '../../../Components/PageHeader';
import Button from '../../../Components/Button';
import RichEditor from '../../../Components/RichEditor';

export default function OficiosCreate({ modelos = [], setores = [] }) {
    // Monta nome hierarquico: "Orgao > Unidade > Setor"
    const buildPath = (s, all) => {
        const parts = [s.nome];
        let p = s.parent_id;
        while (p) {
            const parent = all.find(x => x.id === p);
            if (!parent) break;
            parts.unshift(parent.nome);
            p = parent.parent_id;
        }
        return parts.join(' › ');
    };
    const setoresOrdenados = (setores || []).map(s => ({
        ...s,
        path: buildPath(s, setores || []),
    })).sort((a, b) => a.path.localeCompare(b.path, 'pt-BR'));
    const [modeloId, setModeloId] = useState('');
    const { data, setData, post, processing, errors } = useForm({
        modo_envio: 'fisico', // 'fisico' = livro / 'eletronico' = envio por email
        data_envio: new Date().toISOString().slice(0, 10),
        assunto: '',
        destinatario_nome: '',
        destinatario_email: '',
        destinatario_cargo: '',
        destinatario_orgao: '',
        setor_origem: '',
        conteudo: '',
        files: [],
    });

    const submit = (e) => {
        e.preventDefault();

        const formData = new FormData();
        formData.append('modo_envio', data.modo_envio);
        formData.append('data_envio', data.data_envio);
        formData.append('assunto', data.assunto);
        formData.append('conteudo', data.conteudo);
        formData.append('destinatario_nome', data.destinatario_nome);
        formData.append('destinatario_email', data.destinatario_email);
        formData.append('destinatario_cargo', data.destinatario_cargo);
        formData.append('destinatario_orgao', data.destinatario_orgao);
        formData.append('setor_origem', data.setor_origem);

        if (data.files.length > 0) {
            Array.from(data.files).forEach((file, i) => {
                formData.append(`files[${i}]`, file);
            });
        }

        router.post('/oficios', formData, {
            forceFormData: true,
        });
    };

    const isFisico = data.modo_envio === 'fisico';

    return (
        <AdminLayout>
            <Head title="Novo Oficio" />
            <PageHeader
                title="Novo Oficio"
                subtitle={isFisico ? 'Registro de oficio no livro de controle' : 'Envio eletronico para destinatario externo'}
            >
                <Button variant="secondary" icon="fas fa-arrow-left" href="/oficios/controle">Voltar</Button>
            </PageHeader>

            <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
                {/* Formulario */}
                <div className="lg:col-span-2">
                    <div className="bg-white rounded-xl border border-gray-200 p-6">
                        <form onSubmit={submit} className="space-y-6">
                            {/* Modo de envio */}
                            <div className="bg-gray-50 border border-gray-200 rounded-lg p-3">
                                <label className="block text-xs font-semibold text-gray-700 uppercase tracking-wide mb-2">
                                    Como o oficio sera enviado?
                                </label>
                                <div className="grid grid-cols-1 sm:grid-cols-2 gap-2">
                                    <label className={`flex items-start gap-2 p-3 rounded-lg cursor-pointer border-2 transition-colors
                                        ${isFisico ? 'border-blue-500 bg-blue-50' : 'border-gray-200 bg-white hover:border-gray-300'}`}>
                                        <input type="radio" name="modo_envio" value="fisico"
                                            checked={isFisico} onChange={() => setData('modo_envio', 'fisico')}
                                            className="mt-1" />
                                        <div>
                                            <p className="text-sm font-semibold text-gray-800">
                                                <i className="fas fa-book mr-1 text-blue-600" />Fisico / Impresso
                                            </p>
                                            <p className="text-[11px] text-gray-500 mt-0.5">
                                                Apenas registrar no livro de controle. Sera impresso e entregue manualmente.
                                            </p>
                                        </div>
                                    </label>
                                    <label className={`flex items-start gap-2 p-3 rounded-lg cursor-pointer border-2 transition-colors
                                        ${!isFisico ? 'border-blue-500 bg-blue-50' : 'border-gray-200 bg-white hover:border-gray-300'}`}>
                                        <input type="radio" name="modo_envio" value="eletronico"
                                            checked={!isFisico} onChange={() => setData('modo_envio', 'eletronico')}
                                            className="mt-1" />
                                        <div>
                                            <p className="text-sm font-semibold text-gray-800">
                                                <i className="fas fa-paper-plane mr-1 text-cyan-600" />Eletronico (Email)
                                            </p>
                                            <p className="text-[11px] text-gray-500 mt-0.5">
                                                Enviar por e-mail com rastreio de entrega e abertura.
                                            </p>
                                        </div>
                                    </label>
                                </div>
                            </div>

                            {/* Data do envio (visivel apenas para registro fisico) */}
                            {isFisico && (
                                <div>
                                    <label className="block text-sm font-medium text-gray-700 mb-1">
                                        Data do Oficio
                                    </label>
                                    <input type="date" value={data.data_envio}
                                        onChange={(e) => setData('data_envio', e.target.value)}
                                        className="ds-input w-48" />
                                </div>
                            )}

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
                                    placeholder="Assunto do oficio"
                                    required
                                />
                                {errors.assunto && <p className="text-xs text-red-500 mt-1">{errors.assunto}</p>}
                            </div>

                            {/* Dados do destinatario */}
                            <div className="grid grid-cols-1 sm:grid-cols-2 gap-6">
                                <div>
                                    <label className="block text-sm font-medium text-gray-700 mb-1">
                                        Nome do Destinatario <span className="text-red-500">*</span>
                                    </label>
                                    <input
                                        type="text"
                                        value={data.destinatario_nome}
                                        onChange={(e) => setData('destinatario_nome', e.target.value)}
                                        className="ds-input"
                                        placeholder="Nome completo"
                                        required
                                    />
                                    {errors.destinatario_nome && <p className="text-xs text-red-500 mt-1">{errors.destinatario_nome}</p>}
                                </div>

                                <div>
                                    <label className="block text-sm font-medium text-gray-700 mb-1">
                                        E-mail do Destinatario {!isFisico && <span className="text-red-500">*</span>}
                                        {isFisico && <span className="text-xs text-gray-400 font-normal ml-1">(opcional)</span>}
                                    </label>
                                    <input
                                        type="email"
                                        value={data.destinatario_email}
                                        onChange={(e) => setData('destinatario_email', e.target.value)}
                                        className="ds-input"
                                        placeholder={isFisico ? 'opcional' : 'email@exemplo.com'}
                                        required={!isFisico}
                                    />
                                    {errors.destinatario_email && <p className="text-xs text-red-500 mt-1">{errors.destinatario_email}</p>}
                                </div>

                                <div>
                                    <label className="block text-sm font-medium text-gray-700 mb-1">
                                        Cargo do Destinatario
                                    </label>
                                    <input
                                        type="text"
                                        value={data.destinatario_cargo}
                                        onChange={(e) => setData('destinatario_cargo', e.target.value)}
                                        className="ds-input"
                                        placeholder="Ex: Secretario de Administracao"
                                    />
                                    {errors.destinatario_cargo && <p className="text-xs text-red-500 mt-1">{errors.destinatario_cargo}</p>}
                                </div>

                                <div>
                                    <label className="block text-sm font-medium text-gray-700 mb-1">
                                        Orgao/Instituicao
                                    </label>
                                    <input
                                        type="text"
                                        value={data.destinatario_orgao}
                                        onChange={(e) => setData('destinatario_orgao', e.target.value)}
                                        className="ds-input"
                                        placeholder="Ex: Prefeitura Municipal"
                                    />
                                    {errors.destinatario_orgao && <p className="text-xs text-red-500 mt-1">{errors.destinatario_orgao}</p>}
                                </div>
                            </div>

                            {/* Setor Origem */}
                            <div>
                                <label className="block text-sm font-medium text-gray-700 mb-1">Setor de Origem</label>
                                {setoresOrdenados.length > 0 ? (
                                    <select
                                        value={data.setor_origem}
                                        onChange={(e) => setData('setor_origem', e.target.value)}
                                        className="ds-input">
                                        <option value="">-- selecione um setor do organograma --</option>
                                        {setoresOrdenados.map(s => (
                                            <option key={s.id} value={s.path}>{s.path}</option>
                                        ))}
                                    </select>
                                ) : (
                                    <>
                                        <input
                                            type="text"
                                            value={data.setor_origem}
                                            onChange={(e) => setData('setor_origem', e.target.value)}
                                            className="ds-input"
                                            placeholder="Ex: Departamento de TI"
                                        />
                                        <p className="text-[10px] text-gray-400 mt-1">
                                            <i className="fas fa-info-circle mr-1" />
                                            Cadastre o organograma da sua UG para selecionar setores
                                        </p>
                                    </>
                                )}
                                {errors.setor_origem && <p className="text-xs text-red-500 mt-1">{errors.setor_origem}</p>}
                            </div>

                            {/* Conteudo + selecao de modelo */}
                            <div>
                                <div className="flex items-end justify-between mb-2 gap-2 flex-wrap">
                                    <label className="block text-sm font-medium text-gray-700">
                                        Conteudo <span className="text-red-500">*</span>
                                    </label>
                                    {modelos.length > 0 && (
                                        <div className="flex items-center gap-2">
                                            <label className="text-[10px] text-gray-500 uppercase font-semibold tracking-wide">
                                                Carregar modelo:
                                            </label>
                                            <select
                                                value={modeloId}
                                                onChange={(e) => {
                                                    const id = e.target.value;
                                                    setModeloId(id);
                                                    if (!id) return;
                                                    const m = modelos.find(x => String(x.id) === String(id));
                                                    if (!m) return;
                                                    // Confirma sobrescrita se ja houver conteudo digitado
                                                    if (data.conteudo && data.conteudo.replace(/<[^>]+>/g, '').trim().length > 10) {
                                                        if (!confirm('Substituir o conteudo atual pelo modelo selecionado?')) {
                                                            setModeloId('');
                                                            return;
                                                        }
                                                    }
                                                    setData('conteudo', m.conteudo);
                                                }}
                                                className="text-xs px-2 py-1 border border-gray-200 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-400 max-w-xs">
                                                <option value="">-- selecionar modelo --</option>
                                                {modelos.map(m => (
                                                    <option key={m.id} value={m.id}>
                                                        {m.categoria ? `[${m.categoria}] ` : ''}{m.nome}
                                                    </option>
                                                ))}
                                            </select>
                                        </div>
                                    )}
                                </div>
                                <RichEditor
                                    html={data.conteudo}
                                    onChange={(html) => setData('conteudo', html)}
                                    minHeight={420}
                                    placeholder="Digite o conteudo do oficio ou selecione um modelo acima..."
                                />
                                {errors.conteudo && <p className="text-xs text-red-500 mt-1">{errors.conteudo}</p>}
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
                                <Button variant="secondary" type="button" href="/oficios">Cancelar</Button>
                                <Button
                                    type="submit"
                                    loading={processing}
                                    icon="fas fa-paper-plane"
                                    disabled={!data.destinatario_nome || !data.destinatario_email}
                                >
                                    Enviar Oficio
                                </Button>
                            </div>
                        </form>
                    </div>
                </div>

                {/* Coluna informativa */}
                <div className="space-y-6">
                    <div className="bg-white rounded-xl border border-gray-200 p-5">
                        <h3 className="text-sm font-semibold text-gray-800 mb-3">
                            <i className="fas fa-info-circle text-blue-500 mr-1.5" />
                            {isFisico ? 'Registro no Livro' : 'Envio Eletronico'}
                        </h3>
                        <div className="space-y-3 text-xs text-gray-600 leading-relaxed">
                            {isFisico ? (
                                <>
                                    <p>
                                        O oficio sera <strong>registrado no livro de controle</strong> para
                                        fins de auditoria, sem disparo de e-mail.
                                    </p>
                                    <p>
                                        Apos o cadastro, voce podera imprimir o documento e entrega-lo
                                        ao destinatario por meios fisicos.
                                    </p>
                                </>
                            ) : (
                                <>
                                    <p>
                                        O oficio sera <strong>enviado por e-mail</strong> ao destinatario,
                                        com rastreio de entrega e abertura.
                                    </p>
                                    <p>
                                        Apos o envio, acompanhe o status em tempo real na pagina de detalhes.
                                    </p>
                                </>
                            )}
                        </div>
                    </div>

                    {!isFisico && (
                        <div className="bg-white rounded-xl border border-gray-200 p-5">
                            <h3 className="text-sm font-semibold text-gray-800 mb-3">
                                <i className="fas fa-route text-green-500 mr-1.5" />
                                Rastreamento
                            </h3>
                            <div className="space-y-2">
                                <TrackStep icon="fas fa-paper-plane" color="blue" label="Enviado" desc="Oficio criado e enviado" />
                                <TrackStep icon="fas fa-envelope" color="yellow" label="Entregue" desc="E-mail entregue na caixa" />
                                <TrackStep icon="fas fa-eye" color="green" label="Lido" desc="Destinatario abriu o oficio" />
                                <TrackStep icon="fas fa-reply" color="purple" label="Respondido" desc="Resposta recebida" />
                            </div>
                        </div>
                    )}
                </div>
            </div>
        </AdminLayout>
    );
}

function TrackStep({ icon, color, label, desc }) {
    const colors = {
        blue: 'bg-blue-100 text-blue-600',
        yellow: 'bg-yellow-100 text-yellow-600',
        green: 'bg-green-100 text-green-600',
        purple: 'bg-purple-100 text-purple-600',
    };

    return (
        <div className="flex items-center gap-3">
            <div className={`w-7 h-7 rounded-full flex items-center justify-center shrink-0 ${colors[color]}`}>
                <i className={`${icon} text-[10px]`} />
            </div>
            <div>
                <p className="text-xs font-medium text-gray-700">{label}</p>
                <p className="text-[10px] text-gray-400">{desc}</p>
            </div>
        </div>
    );
}

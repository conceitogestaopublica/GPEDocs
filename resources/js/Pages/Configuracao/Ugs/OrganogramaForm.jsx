/**
 * Cadastro de no do Organograma — tela dedicada (CadastroLayout com resumo lateral).
 *
 * Os campos exibidos sao contextuais ao nivel:
 *   - Nivel 1 (orgao):       tipo (Prefeitura/Camara/Fundo/Autarquia)
 *   - Nivel 2 (unidade):     tipo de fundo
 *   - Nivel 3 (departamento): protocolo externo
 *
 * Campos comuns: codigo, nome, dt_inicio, dt_encerramento, codigo TCE,
 * suprimir TCE, responsavel, endereco proprio/herdado.
 */
import { Head, router, useForm } from '@inertiajs/react';
import AdminLayout from '../../../Layouts/AdminLayout';
import CadastroLayout, { CadastroSecao } from '../../../Components/CadastroLayout';
import EnderecoForm from '../../../Components/EnderecoForm';

const TIPOS_ORGAO = [
    'Prefeitura',
    'Câmara',
    'Fundo',
    'Autarquia',
    'Fundação',
    'Empresa Pública',
    'Sociedade de Economia Mista',
    'Consórcio',
    'Secretaria',
    'Outro',
];

export default function OrganogramaForm({ ug, parent, nivel, node, usuarios = [] }) {
    const isEdit = !! node;

    const labels = { 1: ug.nivel_1_label, 2: ug.nivel_2_label, 3: ug.nivel_3_label };
    const labelAtual = labels[nivel];

    const { data, setData, post, put, processing, errors } = useForm({
        // Identificacao
        codigo:            node?.codigo || '',
        nome:              node?.nome || '',

        // Tipo (contextual)
        tipo_orgao:        node?.tipo_orgao || '',
        tipo_fundo:        node?.tipo_fundo || '',

        // TCE
        codigo_tce:        node?.codigo_tce || '',
        suprimir_tce:      !! node?.suprimir_tce,

        // Vigencia
        dt_inicio:         node?.dt_inicio || '',
        dt_encerramento:   node?.dt_encerramento || '',

        // Responsavel
        responsavel_id:    node?.responsavel_id || '',

        // Protocolo (so nivel 3)
        protocolo_externo: !! node?.protocolo_externo,

        // Endereco
        endereco_proprio:  !! node?.endereco_proprio,
        cep:               node?.cep || '',
        logradouro:        node?.logradouro || '',
        numero:            node?.numero || '',
        complemento:       node?.complemento || '',
        bairro:            node?.bairro || '',
        cidade:            node?.cidade || '',
        uf:                node?.uf || '',

        // Apenas para criacao (vai como query string)
        parent_id:         parent?.id || null,
    });

    const obrigatoriosFaltando = ! data.nome.trim();

    const onSalvar = () => {
        const opts = { preserveScroll: true };
        if (isEdit) put(`/configuracoes/ugs/${ug.id}/organograma/nodes/${node.id}`, opts);
        else        post(`/configuracoes/ugs/${ug.id}/organograma/nodes`, opts);
    };

    const onCancelar = () => router.visit(`/configuracoes/ugs/${ug.id}/organograma`);

    const responsavel = usuarios.find(u => u.id === Number(data.responsavel_id));
    const enderecoStr = data.endereco_proprio
        ? [data.logradouro, data.numero, data.bairro, data.cidade && (data.cidade + (data.uf ? '/' + data.uf : ''))]
              .filter(Boolean).join(', ')
        : 'Mesmo da UG';

    const resumo = [
        { icone: 'fa-layer-group', label: 'Nível',    valor: `${nivel} — ${labelAtual}` },
        ...(parent ? [{ icone: 'fa-arrow-up', label: 'Pai', valor: parent.nome }] : []),
        { icone: 'fa-hashtag',     label: 'Código',   valor: data.codigo, vazio: ! data.codigo },
        { icone: 'fa-tag',         label: 'Nome',     valor: data.nome,   vazio: ! data.nome },
        ...(nivel === 1 ? [{ icone: 'fa-building',  label: 'Tipo',       valor: data.tipo_orgao, vazio: ! data.tipo_orgao }] : []),
        ...(nivel === 2 ? [{ icone: 'fa-coins',     label: 'Tipo Fundo', valor: data.tipo_fundo, vazio: ! data.tipo_fundo }] : []),
        { icone: 'fa-id-card-alt', label: 'Cód. TCE', valor: data.codigo_tce, vazio: ! data.codigo_tce },
        { icone: 'fa-calendar-alt',label: 'Vigência',
          valor: data.dt_inicio ? formatarData(data.dt_inicio) + (data.dt_encerramento ? ' até ' + formatarData(data.dt_encerramento) : ' (sem fim)') : '',
          vazio: ! data.dt_inicio },
        { icone: 'fa-user-tie',    label: 'Responsável', valor: responsavel?.name, vazio: ! responsavel },
        { icone: 'fa-map-marker-alt', label: 'Endereço',  valor: enderecoStr },
        ...(nivel === 3 ? [{ icone: 'fa-envelope-open-text', label: 'Protocolo Externo', valor: data.protocolo_externo ? 'Sim' : 'Não' }] : []),
    ];

    return (
        <AdminLayout>
            <Head title={isEdit ? `Editar ${labelAtual}` : `Novo ${labelAtual}`} />

            <CadastroLayout
                titulo={isEdit ? `Editar ${labelAtual}` : `Novo ${labelAtual}`}
                subtitulo={
                    parent
                        ? `${ug.codigo} · ${ug.nome} > ${parent.nome}`
                        : `${ug.codigo} · ${ug.nome}`
                }
                voltarHref={`/configuracoes/ugs/${ug.id}/organograma`}
                voltarLabel="Voltar para organograma"
                resumo={resumo}
                obrigatoriosFaltando={obrigatoriosFaltando}
                onCancelar={onCancelar}
                onSalvar={onSalvar}
                processing={processing}
                labelSalvar={isEdit ? 'Salvar alteracoes' : `Criar ${labelAtual}`}
                iconeSalvar={isEdit ? 'fas fa-save' : 'fas fa-plus'}
            >
                {/* Identificacao */}
                <CadastroSecao
                    icone="fa-info-circle"
                    titulo="Identificação"
                    descricao={`Dados básicos do ${labelAtual.toLowerCase()}`}
                >
                    <div className="grid grid-cols-3 gap-3">
                        <div>
                            <label className="block text-xs font-medium text-gray-700 mb-1">Código</label>
                            <input type="text" value={data.codigo} onChange={(e) => setData('codigo', e.target.value)}
                                className="ds-input" maxLength={20} placeholder="Ex: 001" />
                        </div>
                        <div className="col-span-2">
                            <label className="block text-xs font-medium text-gray-700 mb-1">
                                Nome <span className="text-red-500">*</span>
                            </label>
                            <input type="text" value={data.nome} onChange={(e) => setData('nome', e.target.value)}
                                className="ds-input" maxLength={200} autoFocus />
                            {errors.nome && <p className="mt-1 text-xs text-red-600">{errors.nome}</p>}
                        </div>
                    </div>

                    {/* Tipo contextual */}
                    {nivel === 1 && (
                        <div>
                            <label className="block text-xs font-medium text-gray-700 mb-1">Tipo do {labelAtual}</label>
                            <select value={data.tipo_orgao} onChange={(e) => setData('tipo_orgao', e.target.value)}
                                className="ds-input">
                                <option value="">— Selecionar —</option>
                                {TIPOS_ORGAO.map(t => <option key={t} value={t}>{t}</option>)}
                            </select>
                        </div>
                    )}
                    {nivel === 2 && (
                        <div>
                            <label className="block text-xs font-medium text-gray-700 mb-1">
                                Tipo de Fundo <span className="text-gray-400 font-normal">(ex: FUNDEB, Tesouro, Próprio)</span>
                            </label>
                            <input type="text" value={data.tipo_fundo} onChange={(e) => setData('tipo_fundo', e.target.value)}
                                className="ds-input" maxLength={50} />
                        </div>
                    )}
                </CadastroSecao>

                {/* Vigencia + TCE */}
                <CadastroSecao
                    icone="fa-calendar-alt"
                    titulo="Vigência e TCE"
                    descricao="Período de existência e código de integração com o TCE/CGE"
                >
                    <div className="grid grid-cols-2 gap-3">
                        <div>
                            <label className="block text-xs font-medium text-gray-700 mb-1">Data de início</label>
                            <input type="date" value={data.dt_inicio} onChange={(e) => setData('dt_inicio', e.target.value)}
                                className="ds-input" />
                        </div>
                        <div>
                            <label className="block text-xs font-medium text-gray-700 mb-1">Data de encerramento</label>
                            <input type="date" value={data.dt_encerramento} onChange={(e) => setData('dt_encerramento', e.target.value)}
                                className="ds-input" />
                            {errors.dt_encerramento && <p className="mt-1 text-xs text-red-600">{errors.dt_encerramento}</p>}
                        </div>
                    </div>
                    <div className="grid grid-cols-3 gap-3 items-end">
                        <div className="col-span-2">
                            <label className="block text-xs font-medium text-gray-700 mb-1">Código TCE</label>
                            <input type="text" value={data.codigo_tce} onChange={(e) => setData('codigo_tce', e.target.value)}
                                className="ds-input" maxLength={20} disabled={data.suprimir_tce} />
                        </div>
                        <label className="flex items-center gap-2 cursor-pointer pb-2">
                            <input type="checkbox" checked={data.suprimir_tce}
                                onChange={(e) => setData('suprimir_tce', e.target.checked)}
                                className="rounded border-gray-300 text-blue-600" />
                            <span className="text-xs text-gray-700">Suprimir Cód. TCE</span>
                        </label>
                    </div>
                </CadastroSecao>

                {/* Responsavel */}
                <CadastroSecao
                    icone="fa-user-tie"
                    titulo="Responsável"
                    descricao="Usuário interno responsável por esta unidade (opcional)"
                >
                    <select value={data.responsavel_id}
                        onChange={(e) => setData('responsavel_id', e.target.value ? Number(e.target.value) : '')}
                        className="ds-input">
                        <option value="">— Sem responsável definido —</option>
                        {usuarios.map(u => (
                            <option key={u.id} value={u.id}>{u.name} — {u.email}</option>
                        ))}
                    </select>
                </CadastroSecao>

                {/* Protocolo externo (so nivel 3) */}
                {nivel === 3 && (
                    <CadastroSecao
                        icone="fa-envelope-open-text"
                        titulo="Protocolo Externo"
                        descricao="Define se este departamento pode receber solicitações vindas do Portal de Serviços (cidadãos/parceiros)"
                    >
                        <label className={`flex items-start gap-3 p-3 rounded-xl border-2 cursor-pointer transition-colors ${
                            data.protocolo_externo ? 'border-emerald-500 bg-emerald-50' : 'border-gray-200 hover:bg-gray-50'
                        }`}>
                            <input type="checkbox" checked={data.protocolo_externo}
                                onChange={(e) => setData('protocolo_externo', e.target.checked)}
                                className="rounded border-gray-300 text-emerald-600 mt-0.5" />
                            <div>
                                <p className="text-sm font-semibold text-gray-800">
                                    Receber protocolos externos
                                </p>
                                <p className="text-[11px] text-gray-500 leading-tight">
                                    Quando ativado, este departamento aparece como destino possível em
                                    solicitações cadastradas por usuários externos.
                                </p>
                            </div>
                        </label>
                    </CadastroSecao>
                )}

                {/* Endereco */}
                <CadastroSecao
                    icone="fa-map-marker-alt"
                    titulo="Endereço"
                    descricao="Por padrão herda o endereço da UG. Marque 'Próprio' para informar um específico"
                >
                    <div className="grid grid-cols-2 gap-2 mb-3">
                        <label className={`flex items-start gap-2 p-2 rounded-lg border-2 cursor-pointer transition-colors ${
                            ! data.endereco_proprio ? 'border-blue-500 bg-blue-50' : 'border-gray-200 hover:bg-gray-50'
                        }`}>
                            <input type="radio" checked={! data.endereco_proprio}
                                onChange={() => setData('endereco_proprio', false)} className="hidden" />
                            <i className="fas fa-link text-blue-600 mt-0.5" />
                            <div>
                                <p className="text-xs font-semibold text-gray-800">Mesmo da UG</p>
                                <p className="text-[10px] text-gray-500">Herda automaticamente</p>
                            </div>
                        </label>
                        <label className={`flex items-start gap-2 p-2 rounded-lg border-2 cursor-pointer transition-colors ${
                            data.endereco_proprio ? 'border-purple-500 bg-purple-50' : 'border-gray-200 hover:bg-gray-50'
                        }`}>
                            <input type="radio" checked={data.endereco_proprio}
                                onChange={() => setData('endereco_proprio', true)} className="hidden" />
                            <i className="fas fa-edit text-purple-600 mt-0.5" />
                            <div>
                                <p className="text-xs font-semibold text-gray-800">Próprio</p>
                                <p className="text-[10px] text-gray-500">Informar específico</p>
                            </div>
                        </label>
                    </div>

                    {data.endereco_proprio && (
                        <EnderecoForm data={data} setData={setData} errors={errors} />
                    )}
                </CadastroSecao>
            </CadastroLayout>
        </AdminLayout>
    );
}

function formatarData(iso) {
    if (! iso) return '';
    const [a, m, d] = iso.split('-');
    return `${d}/${m}/${a}`;
}

/**
 * Cadastro de Unidade Gestora — tela dedicada (padrao "wizard com resumo lateral")
 */
import { Head, router, useForm } from '@inertiajs/react';
import AdminLayout from '../../../Layouts/AdminLayout';
import CadastroLayout, { CadastroSecao } from '../../../Components/CadastroLayout';
import EnderecoForm from '../../../Components/EnderecoForm';

export default function UgForm({ ug }) {
    const isEdit = !! ug;

    const { data, setData, post, put, processing, errors } = useForm({
        codigo:        ug?.codigo || '',
        nome:          ug?.nome || '',
        cnpj:          ug?.cnpj || '',
        cep:           ug?.cep || '',
        logradouro:    ug?.logradouro || '',
        numero:        ug?.numero || '',
        complemento:   ug?.complemento || '',
        bairro:        ug?.bairro || '',
        cidade:        ug?.cidade || '',
        uf:            ug?.uf || '',
        nivel_1_label: ug?.nivel_1_label || 'Órgão',
        nivel_2_label: ug?.nivel_2_label || 'Unidade',
        nivel_3_label: ug?.nivel_3_label || 'Setor',
        observacoes:   ug?.observacoes || '',
    });

    const obrigatoriosFaltando =
        ! data.codigo.trim() ||
        ! data.nome.trim() ||
        ! data.nivel_1_label.trim() ||
        ! data.nivel_2_label.trim() ||
        ! data.nivel_3_label.trim();

    const onSalvar = () => {
        const opts = { preserveScroll: true };
        if (isEdit) put(`/configuracoes/ugs/${ug.id}`, opts);
        else        post('/configuracoes/ugs', opts);
    };

    const onCancelar = () => router.visit('/configuracoes/ugs');

    const enderecoStr = [data.logradouro, data.numero, data.bairro].filter(Boolean).join(', ');
    const cidadeUf    = [data.cidade, data.uf].filter(Boolean).join('/');

    const resumo = [
        { icone: 'fa-hashtag',     label: 'Codigo',     valor: data.codigo, vazio: ! data.codigo },
        { icone: 'fa-building',    label: 'Nome',       valor: data.nome,   vazio: ! data.nome },
        { icone: 'fa-id-card-alt', label: 'CNPJ',       valor: data.cnpj,   vazio: ! data.cnpj },
        { icone: 'fa-map-marker-alt', label: 'Endereco', valor: enderecoStr, vazio: ! enderecoStr },
        { icone: 'fa-city',        label: 'Cidade',     valor: cidadeUf,    vazio: ! cidadeUf },
        { icone: 'fa-sitemap',     label: 'Niveis',
          valor: `${data.nivel_1_label} > ${data.nivel_2_label} > ${data.nivel_3_label}` },
    ];

    return (
        <AdminLayout>
            <Head title={isEdit ? `Editar ${ug.nome}` : 'Nova UG'} />

            <CadastroLayout
                titulo={isEdit ? 'Editar Unidade Gestora' : 'Nova Unidade Gestora'}
                subtitulo={isEdit ? `Atualize os dados de ${ug.nome}` : 'Cadastre uma UG e configure os niveis do organograma'}
                voltarHref="/configuracoes/ugs"
                voltarLabel="Voltar para UGs"
                resumo={resumo}
                obrigatoriosFaltando={obrigatoriosFaltando}
                onCancelar={onCancelar}
                onSalvar={onSalvar}
                processing={processing}
                labelSalvar={isEdit ? 'Salvar alteracoes' : 'Criar UG'}
                iconeSalvar={isEdit ? 'fas fa-save' : 'fas fa-plus'}
            >
                <CadastroSecao
                    icone="fa-info-circle"
                    titulo="Identificacao"
                    descricao="Codigo, nome e CNPJ da UG"
                >
                    <div className="grid grid-cols-3 gap-3">
                        <div>
                            <label className="block text-xs font-medium text-gray-700 mb-1">
                                Codigo <span className="text-red-500">*</span>
                            </label>
                            <input type="text" value={data.codigo} onChange={(e) => setData('codigo', e.target.value)}
                                className="ds-input" maxLength={20} placeholder="Ex: 0001" />
                            {errors.codigo && <p className="mt-1 text-xs text-red-600">{errors.codigo}</p>}
                        </div>
                        <div className="col-span-2">
                            <label className="block text-xs font-medium text-gray-700 mb-1">
                                Nome <span className="text-red-500">*</span>
                            </label>
                            <input type="text" value={data.nome} onChange={(e) => setData('nome', e.target.value)}
                                className="ds-input" maxLength={200} placeholder="Ex: Prefeitura Municipal de Brumadinho" />
                            {errors.nome && <p className="mt-1 text-xs text-red-600">{errors.nome}</p>}
                        </div>
                    </div>
                    <div>
                        <label className="block text-xs font-medium text-gray-700 mb-1">CNPJ</label>
                        <input type="text" value={data.cnpj} onChange={(e) => setData('cnpj', e.target.value)}
                            className="ds-input max-w-xs" maxLength={18} placeholder="00.000.000/0000-00" />
                        {errors.cnpj && <p className="mt-1 text-xs text-red-600">{errors.cnpj}</p>}
                    </div>
                </CadastroSecao>

                <CadastroSecao
                    icone="fa-map-marker-alt"
                    titulo="Endereco"
                    descricao="Endereco principal — sera herdado pelas unidades do organograma quando elas nao tiverem endereco proprio"
                >
                    <EnderecoForm data={data} setData={setData} errors={errors} />
                </CadastroSecao>

                <CadastroSecao
                    icone="fa-sitemap"
                    titulo="Niveis do Organograma"
                    descricao='Cada UG pode usar uma nomenclatura diferente. Exemplos: "Secretaria/Departamento/Setor", "Diretoria/Coordenacao/Nucleo"'
                >
                    <div className="grid grid-cols-3 gap-3">
                        {[1, 2, 3].map(n => (
                            <div key={n}>
                                <label className="block text-xs font-medium text-gray-700 mb-1">
                                    Nivel {n} <span className="text-red-500">*</span>
                                </label>
                                <input type="text" value={data[`nivel_${n}_label`]}
                                    onChange={(e) => setData(`nivel_${n}_label`, e.target.value)}
                                    className="ds-input" maxLength={60} />
                                {errors[`nivel_${n}_label`] && <p className="mt-1 text-xs text-red-600">{errors[`nivel_${n}_label`]}</p>}
                            </div>
                        ))}
                    </div>
                    <div className="bg-blue-50 border border-blue-200 rounded-lg p-3">
                        <p className="text-[11px] text-blue-800">
                            <i className="fas fa-arrow-down mr-1" />
                            Hierarquia: <strong>{data.nivel_1_label}</strong> contem
                            <strong> {data.nivel_2_label}</strong>, que contem
                            <strong> {data.nivel_3_label}</strong>.
                        </p>
                    </div>
                </CadastroSecao>

                <CadastroSecao
                    icone="fa-clipboard"
                    titulo="Observacoes"
                    descricao="Notas livres sobre a UG (opcional)"
                >
                    <textarea value={data.observacoes} onChange={(e) => setData('observacoes', e.target.value)}
                        className="ds-input !h-auto" rows={3} placeholder="Anote informacoes adicionais..." />
                </CadastroSecao>
            </CadastroLayout>
        </AdminLayout>
    );
}

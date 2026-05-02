/**
 * Cadastro de Unidade Gestora — tela dedicada (padrao "wizard com resumo lateral")
 */
import { Head, router, useForm } from '@inertiajs/react';
import { useState } from 'react';
import AdminLayout from '../../../Layouts/AdminLayout';
import CadastroLayout, { CadastroSecao } from '../../../Components/CadastroLayout';
import EnderecoForm from '../../../Components/EnderecoForm';

export default function UgForm({ ug }) {
    const isEdit = !! ug;

    const { data, setData, post, processing, errors } = useForm({
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
        telefone:            ug?.telefone || '',
        email_institucional: ug?.email_institucional || '',
        site:                ug?.site || '',
        brasao:              null, // arquivo novo (File) — opcional
        remover_brasao:      false,
        nivel_1_label: ug?.nivel_1_label || 'Órgão',
        nivel_2_label: ug?.nivel_2_label || 'Unidade',
        nivel_3_label: ug?.nivel_3_label || 'Setor',
        observacoes:   ug?.observacoes || '',
    });

    const [brasaoPreview, setBrasaoPreview] = useState(null);

    const handleBrasaoChange = (e) => {
        const file = e.target.files?.[0] ?? null;
        setData(d => ({ ...d, brasao: file, remover_brasao: false }));
        if (file) {
            const reader = new FileReader();
            reader.onload = ev => setBrasaoPreview(ev.target.result);
            reader.readAsDataURL(file);
        } else {
            setBrasaoPreview(null);
        }
    };

    const obrigatoriosFaltando =
        ! data.codigo.trim() ||
        ! data.nome.trim() ||
        ! data.nivel_1_label.trim() ||
        ! data.nivel_2_label.trim() ||
        ! data.nivel_3_label.trim();

    const onSalvar = () => {
        const url = isEdit ? `/configuracoes/ugs/${ug.id}` : '/configuracoes/ugs';
        // Sempre POST com FormData (Laravel le _method=PUT)
        const fd = new FormData();
        Object.entries(data).forEach(([k, v]) => {
            if (k === 'brasao') {
                if (v instanceof File) fd.append('brasao', v);
            } else if (typeof v === 'boolean') {
                fd.append(k, v ? '1' : '0');
            } else if (v !== null && v !== undefined) {
                fd.append(k, v);
            }
        });
        if (isEdit) fd.append('_method', 'PUT');
        router.post(url, fd, { preserveScroll: true, forceFormData: true });
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
                    icone="fa-image"
                    titulo="Identidade Visual"
                    descricao="Brasao oficial — usado nos cabecalhos dos PDFs (memorando, oficio, circular, decisao de processo)"
                >
                    <div className="flex items-start gap-4">
                        <div className="w-32 h-32 rounded-xl border-2 border-dashed border-gray-300 bg-gray-50 flex items-center justify-center overflow-hidden shrink-0">
                            {brasaoPreview ? (
                                <img src={brasaoPreview} alt="Preview" className="max-w-full max-h-full object-contain" />
                            ) : ug?.brasao_url && ! data.remover_brasao ? (
                                <img src={ug.brasao_url} alt="Brasao atual" className="max-w-full max-h-full object-contain" />
                            ) : (
                                <i className="fas fa-shield-alt text-3xl text-gray-300" />
                            )}
                        </div>
                        <div className="flex-1 space-y-2">
                            <label className="block text-xs font-medium text-gray-700">Enviar imagem (PNG ou JPG, recomendado &lt; 200KB)</label>
                            <input type="file" accept="image/png,image/jpeg,image/jpg"
                                onChange={handleBrasaoChange}
                                className="block w-full text-xs text-gray-600 file:mr-3 file:py-1.5 file:px-3 file:rounded-lg file:border-0 file:text-xs file:font-medium file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100" />
                            {errors.brasao && <p className="text-xs text-red-600">{errors.brasao}</p>}
                            {ug?.brasao_url && ! data.brasao && (
                                <label className="flex items-center gap-2 cursor-pointer text-xs text-gray-600">
                                    <input type="checkbox" checked={data.remover_brasao}
                                        onChange={(e) => setData('remover_brasao', e.target.checked)}
                                        className="rounded border-gray-300 text-red-600" />
                                    Remover brasao atual
                                </label>
                            )}
                            <p className="text-[10px] text-gray-400">
                                <i className="fas fa-info-circle mr-1" />
                                A imagem aparece a esquerda do cabecalho dos PDFs, com tamanho aproximado de 60px de largura.
                            </p>
                        </div>
                    </div>
                </CadastroSecao>

                <CadastroSecao
                    icone="fa-phone"
                    titulo="Contato Institucional"
                    descricao="Telefone, e-mail e site oficiais — exibidos no cabecalho dos PDFs"
                >
                    <div className="grid grid-cols-2 gap-3">
                        <div>
                            <label className="block text-xs font-medium text-gray-700 mb-1">Telefone(s)</label>
                            <input type="text" value={data.telefone} onChange={(e) => setData('telefone', e.target.value)}
                                className="ds-input" maxLength={50} placeholder="(35) 3267-1155 - (35) 3267-1888" />
                            {errors.telefone && <p className="mt-1 text-xs text-red-600">{errors.telefone}</p>}
                        </div>
                        <div>
                            <label className="block text-xs font-medium text-gray-700 mb-1">Site oficial</label>
                            <input type="text" value={data.site} onChange={(e) => setData('site', e.target.value)}
                                className="ds-input" maxLength={150} placeholder="www.exemplo.mg.gov.br" />
                            {errors.site && <p className="mt-1 text-xs text-red-600">{errors.site}</p>}
                        </div>
                    </div>
                    <div>
                        <label className="block text-xs font-medium text-gray-700 mb-1">E-mail institucional</label>
                        <input type="email" value={data.email_institucional} onChange={(e) => setData('email_institucional', e.target.value)}
                            className="ds-input" maxLength={150} placeholder="contato@exemplo.mg.gov.br" />
                        {errors.email_institucional && <p className="mt-1 text-xs text-red-600">{errors.email_institucional}</p>}
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

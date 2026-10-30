/**
 * Novo Oficio Eletronico — GED
 *
 * Formulario de criacao de oficio para destinatario externo.
 */
import { useEffect, useState } from 'react';
import { Head, router, useForm, usePage } from '@inertiajs/react';
import EmNomeDe from '../../../Components/EmNomeDe';
import AdminLayout from '../../../Layouts/AdminLayout';
import PageHeader from '../../../Components/PageHeader';
import Button from '../../../Components/Button';
import RichEditor from '../../../Components/RichEditor';
import useRascunho from '../../../Hooks/useRascunho';
import FerramentasDoTexto from '../../../Components/FerramentasDoTexto';
import { AnexosRascunho, EstadoRascunho, comRascunho, dadosDoRascunho } from '../../../Components/RascunhoAutomatico';

export default function OficiosCreate({ tramitaAtivo = false, setores = [], autorizacoes = [], variaveis = [], rascunho = null }) {
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
    const { data, setData, post, processing, errors } = useForm(comRascunho(rascunho, {
        modo_envio: 'fisico', // 'fisico' = livro / 'eletronico' = envio por email
        data_envio: new Date().toISOString().slice(0, 10),
        assunto: '',
        destinatario_nome: '',
        destinatario_email: '',
        destinatario_cargo: '',
        destinatario_orgao: '',
        destinatario_celular: '',
        avisar_sms: false,
        setor_origem: '',
        em_nome_de: '',
        contrassenha: '',
        conteudo: '',
        files: [],
        tramita: null, // destino no Tramita GOV.BR {repositorio, estrutura, nome}
    }));

    // Rascunho automático do setor (TR 4.16.6–4.16.8)
    const r = useRascunho({ modulo: 'oficio', dados: dadosDoRascunho(data), titulo: data.assunto, inicial: rascunho, temConteudo: !! (data.assunto || data.conteudo) });

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
        formData.append('destinatario_celular', data.destinatario_celular || '');
        formData.append('avisar_sms', data.avisar_sms && isEmail ? '1' : '0');
        formData.append('setor_origem', data.setor_origem);
        if (isTramita && data.tramita) {
            formData.append('tramita_repositorio', data.tramita.repositorio || '');
            formData.append('tramita_estrutura', data.tramita.estrutura);
            formData.append('tramita_nome', data.tramita.nome);
        }
        if (data.em_nome_de) {
            formData.append('em_nome_de', data.em_nome_de);
            formData.append('contrassenha', data.contrassenha);
        }

        if (data.files.length > 0) {
            Array.from(data.files).forEach((file, i) => {
                formData.append(`files[${i}]`, file);
            });
        }

        if (r.id) formData.append('rascunho_id', r.id);

        router.post('/oficios', formData, {
            forceFormData: true,
        });
    };

    const isFisico = data.modo_envio === 'fisico';
    const isTramita = data.modo_envio === 'tramita';
    const isEmail = data.modo_envio === 'eletronico';

    return (
        <AdminLayout>
            <Head title="Novo Ofício" />
            <PageHeader
                title="Novo Ofício"
                subtitle={isFisico ? 'Registro de ofício no livro de controle' : isTramita ? 'Envio a outro ente público pelo Tramita GOV.BR' : 'Envio eletrônico para destinatário externo'}
            >
                <Button variant="secondary" icon="fas fa-arrow-left" href="/oficios/controle">Voltar</Button>
            </PageHeader>

            <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
                {/* Formulario */}
                <div className="lg:col-span-2">
                    <div className="bg-white rounded-xl border border-gray-200 p-6">
                        <form onSubmit={submit} className="space-y-6">
                            <EstadoRascunho r={r} />
                            <fieldset disabled={!! r.travadoPor} className="space-y-6 min-w-0">
                            <EmNomeDe autorizacoes={autorizacoes} data={data} setData={setData} errors={usePage().props.errors} />
                            {/* Modo de envio */}
                            <div className="bg-gray-50 border border-gray-200 rounded-lg p-3">
                                <label className="block text-sm font-medium text-navy-900 mb-2">
                                    Como o ofício será enviado?
                                </label>
                                <div className={`grid grid-cols-1 gap-2 ${tramitaAtivo ? 'sm:grid-cols-3' : 'sm:grid-cols-2'}`}>
                                    <label className={`flex items-start gap-2 p-3 rounded-lg cursor-pointer border-2 transition-colors
                                        ${isFisico ? 'border-blue-500 bg-blue-50' : 'border-gray-200 bg-white hover:border-gray-300'}`}>
                                        <input type="radio" name="modo_envio" value="fisico"
                                            checked={isFisico} onChange={() => setData('modo_envio', 'fisico')}
                                            className="mt-1" />
                                        <div>
                                            <p className="text-sm font-semibold text-gray-800">
                                                <i className="fas fa-book mr-1 text-blue-600" />Físico / Impresso
                                            </p>
                                            <p className="text-[11px] text-gray-500 mt-0.5">
                                                Apenas registrar no livro de controle. Será impresso e entregue manualmente.
                                            </p>
                                        </div>
                                    </label>
                                    <label className={`flex items-start gap-2 p-3 rounded-lg cursor-pointer border-2 transition-colors
                                        ${isEmail ? 'border-blue-500 bg-blue-50' : 'border-gray-200 bg-white hover:border-gray-300'}`}>
                                        <input type="radio" name="modo_envio" value="eletronico"
                                            checked={isEmail} onChange={() => setData('modo_envio', 'eletronico')}
                                            className="mt-1" />
                                        <div>
                                            <p className="text-sm font-semibold text-gray-800">
                                                <i className="fas fa-paper-plane mr-1 text-cyan-600" />Eletrônico (Email)
                                            </p>
                                            <p className="text-[11px] text-gray-500 mt-0.5">
                                                Enviar por e-mail com rastreio de entrega e abertura.
                                            </p>
                                        </div>
                                    </label>
                                    {tramitaAtivo && (
                                        <label className={`flex items-start gap-2 p-3 rounded-lg cursor-pointer border-2 transition-colors
                                            ${isTramita ? 'border-blue-500 bg-blue-50' : 'border-gray-200 bg-white hover:border-gray-300'}`}>
                                            <input type="radio" name="modo_envio" value="tramita" checked={isTramita} onChange={() => setData('modo_envio', 'tramita')} className="mt-1" />
                                            <div>
                                                <p className="text-sm font-semibold text-gray-800"><i className="fas fa-share-nodes mr-1 text-green-700" />Tramita GOV.BR</p>
                                                <p className="text-[11px] text-gray-500 mt-0.5">Enviar a outro ente público pelo barramento, com NRE, IDT e recibo.</p>
                                            </div>
                                        </label>
                                    )}
                                </div>
                            </div>

                            {/* Data do envio (visivel apenas para registro fisico) */}
                            {isFisico && (
                                <div>
                                    <label className="block text-sm font-medium text-gray-700 mb-1">
                                        Data do Ofício
                                    </label>
                                    <input type="date" value={data.data_envio}
                                        onChange={(e) => setData('data_envio', e.target.value)}
                                        className="ds-input w-48" />
                                </div>
                            )}

                            {isTramita && <DestinoTramita valor={data.tramita} erro={errors.tramita_estrutura || errors.tramita_nome}
                                onEscolher={(e) => setData((d) => ({ ...d, tramita: e, destinatario_orgao: d.destinatario_orgao || e?.nome || '', destinatario_nome: d.destinatario_nome || (e ? 'Protocolo' : '') }))} />}

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
                                    placeholder="Assunto do ofício"
                                    required
                                />
                                {errors.assunto && <p className="text-xs text-red-500 mt-1">{errors.assunto}</p>}
                            </div>

                            {/* Dados do destinatario */}
                            <div className="grid grid-cols-1 sm:grid-cols-2 gap-6">
                                <div>
                                    <label className="block text-sm font-medium text-gray-700 mb-1">
                                        Nome do Destinatário <span className="text-red-500">*</span>
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
                                        E-mail do Destinatário {isEmail && <span className="text-red-500">*</span>}
                                        {! isEmail && <span className="text-xs text-gray-400 font-normal ml-1">(opcional)</span>}
                                    </label>
                                    <input
                                        type="email"
                                        value={data.destinatario_email}
                                        onChange={(e) => setData('destinatario_email', e.target.value)}
                                        className="ds-input"
                                        placeholder={isEmail ? 'email@exemplo.com' : 'opcional'}
                                        required={isEmail}
                                    />
                                    {errors.destinatario_email && <p className="text-xs text-red-500 mt-1">{errors.destinatario_email}</p>}
                                </div>

                                <div>
                                    <label className="block text-sm font-medium text-gray-700 mb-1">
                                        Cargo do Destinatário
                                    </label>
                                    <input
                                        type="text"
                                        value={data.destinatario_cargo}
                                        onChange={(e) => setData('destinatario_cargo', e.target.value)}
                                        className="ds-input"
                                        placeholder="Ex: Secretário de Administração"
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

                            {/* SMS junto com o envio oficial por e-mail (TR 4.47.2) */}
                            {isEmail && (
                                <div className="grid grid-cols-1 sm:grid-cols-2 gap-4 items-end">
                                    <div>
                                        <label className="block text-sm font-medium text-gray-700 mb-1">Celular do destinatário</label>
                                        <input type="tel" value={data.destinatario_celular} onChange={(e) => setData('destinatario_celular', e.target.value)}
                                            className="ds-input" placeholder="(31) 99999-0000" />
                                        {errors.destinatario_celular && <p className="text-xs text-red-500 mt-1">{errors.destinatario_celular}</p>}
                                    </div>
                                    <label className={`flex items-center gap-2 text-sm pb-2 ${data.destinatario_celular ? 'text-gray-700 cursor-pointer' : 'text-gray-400'}`}>
                                        <input type="checkbox" disabled={! data.destinatario_celular} checked={data.avisar_sms && !! data.destinatario_celular}
                                            onChange={(e) => setData('avisar_sms', e.target.checked)} className="rounded border-gray-300 text-cyan-600" />
                                        Avisar também por SMS, com o link do ofício
                                    </label>
                                </div>
                            )}

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
                                        Conteúdo <span className="text-red-500">*</span>
                                    </label>
                                    <FerramentasDoTexto modulo="oficio" conteudo={data.conteudo} onAplicar={(html) => setData('conteudo', html)}
                                        dadosPrevia={() => dadosDoRascunho(data)} desabilitado={!! r.travadoPor} />
                                </div>
                                <RichEditor somenteLeitura={!! r.travadoPor}
                                    html={data.conteudo}
                                    onChange={(html) => setData('conteudo', html)}
                                    minHeight={420} variaveis={variaveis} marcadores={['mencao', 'referencia', 'assinatura']} erro={errors.conteudo}
                                    placeholder="Digite o conteúdo do ofício ou selecione um modelo acima..."
                                />
                                {errors.conteudo && <p className="text-xs text-red-500 mt-1">{errors.conteudo}</p>}
                            </div>

                            {/* Anexos */}
                            <AnexosRascunho r={r} />

                            {/* Acoes */}
                            <div className="flex justify-end gap-3 pt-4 border-t border-gray-100">
                                <Button variant="secondary" type="button" href="/oficios">Cancelar</Button>
                                <Button
                                    type="submit"
                                    loading={processing}
                                    icon="fas fa-paper-plane"
                                    disabled={! data.destinatario_nome || (isEmail && ! data.destinatario_email) || (isTramita && ! data.tramita)}
                                >
                                    Enviar Ofício
                                </Button>
                            </div>
                            </fieldset>
                        </form>
                    </div>
                </div>

                {/* Coluna informativa */}
                <div className="space-y-6">
                    <div className="bg-white rounded-xl border border-gray-200 p-5">
                        <h3 className="text-sm font-semibold text-gray-800 mb-3">
                            <i className="fas fa-info-circle text-blue-500 mr-1.5" />
                            {isFisico ? 'Registro no Livro' : 'Envio Eletrônico'}
                        </h3>
                        <div className="space-y-3 text-xs text-gray-600 leading-relaxed">
                            {isFisico ? (
                                <>
                                    <p>
                                        O ofício será <strong>registrado no livro de controle</strong> para
                                        fins de auditoria, sem disparo de e-mail.
                                    </p>
                                    <p>
                                        Após o cadastro, você poderá imprimir o documento e entrega-lo
                                        ao destinatário por meios físicos.
                                    </p>
                                </>
                            ) : (
                                <>
                                    <p>
                                        O ofício será <strong>enviado por e-mail</strong> ao destinatário,
                                        com rastreio de entrega e abertura.
                                    </p>
                                    <p>
                                        Após o envio, acompanhe o status em tempo real na página de detalhes.
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
                                <TrackStep icon="fas fa-paper-plane" color="blue" label="Enviado" desc="Ofício criado e enviado" />
                                <TrackStep icon="fas fa-envelope" color="yellow" label="Entregue" desc="E-mail entregue na caixa" />
                                <TrackStep icon="fas fa-eye" color="green" label="Lido" desc="Destinatário abriu o ofício" />
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

/** Destino no Tramita GOV.BR: busca da estrutura (órgão/unidade) no barramento (TR 4.28.5). */
function DestinoTramita({ valor, onEscolher, erro }) {
    const [busca, setBusca] = useState('');
    const [lista, setLista] = useState([]);
    const [falha, setFalha] = useState('');
    useEffect(() => {
        if (valor) return undefined;
        const t = setTimeout(() => fetch(`/tramita/estruturas?q=${encodeURIComponent(busca)}`, { headers: { Accept: 'application/json' } })
            .then((r) => r.json()).then((j) => (Array.isArray(j) ? (setLista(j), setFalha('')) : setFalha(j.erro || 'Erro ao consultar o barramento.')))
            .catch(() => setFalha('Erro ao consultar o barramento.')), 300);
        return () => clearTimeout(t);
    }, [busca, valor]);

    return (
        <div className="rounded-lg border border-green-200 bg-green-50 p-3">
            <label className="mb-1 block text-sm font-medium text-gray-700">Órgão de destino no Tramita GOV.BR <span className="text-red-500">*</span></label>
            {valor ? (
                <div className="flex items-center gap-2 text-sm">
                    <i className="fas fa-building-columns text-green-700" /><b className="flex-1">{valor.nome}</b>
                    <span className="text-xs text-gray-500">estrutura {valor.estrutura}</span>
                    <button type="button" onClick={() => onEscolher(null)} className="text-xs text-red-600 hover:underline">trocar</button>
                </div>
            ) : (
                <>
                    <input value={busca} onChange={(e) => setBusca(e.target.value)} placeholder="Buscar órgão ou unidade (ex.: Ministério, Secretaria…)" className="ds-input" />
                    {falha && <p className="mt-1 text-xs text-red-600">{falha}</p>}
                    <ul className="mt-1 max-h-48 divide-y divide-green-100 overflow-auto rounded border border-green-100 bg-white">
                        {lista.map((e) => (
                            <li key={`${e.repositorio}-${e.estrutura}`}><button type="button" onClick={() => onEscolher(e)} className="w-full px-3 py-1.5 text-left text-sm hover:bg-green-50">
                                {e.nome}<span className="block text-[11px] text-gray-500">repositório {e.repositorio} · estrutura {e.estrutura}</span></button></li>
                        ))}
                    </ul>
                </>
            )}
            {erro && <p className="mt-1 text-xs text-red-500">{erro}</p>}
            <p className="mt-1 text-[11px] text-gray-600">O PDF do ofício e os anexos vão pelo barramento; o NRE, o IDT e o recibo ficam registrados no ofício.</p>
        </div>
    );
}

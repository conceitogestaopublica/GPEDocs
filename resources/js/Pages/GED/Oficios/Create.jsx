/**
 * Novo Oficio Eletronico — GED
 *
 * Formulario de criacao de oficio para destinatario externo.
 */
import { Head, router, useForm } from '@inertiajs/react';
import AdminLayout from '../../../Layouts/AdminLayout';
import PageHeader from '../../../Components/PageHeader';
import Button from '../../../Components/Button';

export default function OficiosCreate() {
    const { data, setData, post, processing, errors } = useForm({
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

    return (
        <AdminLayout>
            <Head title="Novo Oficio Eletronico" />
            <PageHeader
                title="Novo Oficio Eletronico"
                subtitle="Enviar documento oficial para destinatario externo"
            >
                <Button variant="secondary" icon="fas fa-arrow-left" href="/oficios">Voltar</Button>
            </PageHeader>

            <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
                {/* Formulario */}
                <div className="lg:col-span-2">
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
                                        E-mail do Destinatario <span className="text-red-500">*</span>
                                    </label>
                                    <input
                                        type="email"
                                        value={data.destinatario_email}
                                        onChange={(e) => setData('destinatario_email', e.target.value)}
                                        className="ds-input"
                                        placeholder="email@exemplo.com"
                                        required
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
                                <input
                                    type="text"
                                    value={data.setor_origem}
                                    onChange={(e) => setData('setor_origem', e.target.value)}
                                    className="ds-input"
                                    placeholder="Ex: Departamento de TI"
                                />
                                {errors.setor_origem && <p className="text-xs text-red-500 mt-1">{errors.setor_origem}</p>}
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
                                    rows={12}
                                    placeholder="Digite o conteudo do oficio..."
                                    required
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
                            Sobre Oficios Eletronicos
                        </h3>
                        <div className="space-y-3 text-xs text-gray-600 leading-relaxed">
                            <p>
                                O oficio eletronico e um documento oficial enviado para destinatarios
                                externos via e-mail, com rastreio de entrega e abertura.
                            </p>
                            <p>
                                Apos o envio, voce podera acompanhar o status de entrega e leitura
                                em tempo real na pagina de detalhes do oficio.
                            </p>
                        </div>
                    </div>

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

/**
 * Certificado da Prefeitura — e-CNPJ A1 por UG que sela os PDFs assinados por link.
 */
import { Head, router, useForm } from '@inertiajs/react';
import { useState } from 'react';
import AdminLayout from '../../../Layouts/AdminLayout';
import PageHeader from '../../../Components/PageHeader';
import Button from '../../../Components/Button';
import Card from '../../../Components/Card';
import Modal from '../../../Components/Modal';

export default function CertificadoInstitucionalIndex({ ugs = [], aceita_teste }) {
    const [enviandoPara, setEnviandoPara] = useState(null);

    return (
        <AdminLayout>
            <Head title="Certificado da Prefeitura" />
            <PageHeader title="Certificado da Prefeitura"
                subtitle="e-CNPJ A1 que sela os documentos assinados por link — qualquer alteração no PDF invalida o selo" />

            <Card>
                <p className="text-sm text-gray-600 mb-4">
                    Com o certificado cadastrado, todo PDF assinado por um signatário externo sai <strong>assinado digitalmente
                    pela prefeitura</strong> (selo), com a página de termo de evidências no final. Sem certificado, o PDF sai só
                    com o carimbo e o termo. O arquivo .pfx e a senha ficam guardados cifrados.
                    {aceita_teste && <span className="block mt-1 text-amber-700">Ambiente local: aceita certificado de teste (fora da ICP-Brasil). Em produção, só e-CNPJ ICP-Brasil.</span>}
                </p>

                <table className="w-full text-sm">
                    <thead className="bg-gray-50 text-gray-500 text-xs">
                        <tr>
                            <th className="px-4 py-3 text-left font-semibold">Unidade gestora</th>
                            <th className="px-4 py-3 text-left font-semibold">Certificado</th>
                            <th className="px-4 py-3 text-left font-semibold">Validade</th>
                            <th className="px-4 py-3 text-left font-semibold">Situação</th>
                            <th className="px-4 py-3 text-right font-semibold">Ações</th>
                        </tr>
                    </thead>
                    <tbody className="divide-y divide-gray-100">
                        {ugs.map(ug => {
                            const c = ug.certificado;
                            return (
                                <tr key={ug.id} className="hover:bg-gray-50">
                                    <td className="px-4 py-3">
                                        <p className="font-medium text-gray-800">{ug.nome}</p>
                                        <p className="text-xs text-gray-400">{ug.codigo}</p>
                                    </td>
                                    <td className="px-4 py-3">
                                        {c ? (
                                            <>
                                                <p className="text-gray-800">{c.titular}</p>
                                                <p className="text-xs text-gray-500">
                                                    {c.cnpj && <>CNPJ {c.cnpj} · </>}{c.emissor}
                                                    {! c.icp_brasil && <span className="ml-1 text-amber-700 font-semibold">(teste — fora da ICP-Brasil)</span>}
                                                </p>
                                                {c.enviado_por && <p className="text-[11px] text-gray-400">Enviado por {c.enviado_por} em {c.enviado_em}</p>}
                                            </>
                                        ) : <span className="text-gray-400">Nenhum</span>}
                                    </td>
                                    <td className="px-4 py-3 text-gray-600">{c?.valido_ate || '-'}</td>
                                    <td className="px-4 py-3">
                                        {! c ? <span className="text-xs px-2 py-0.5 rounded-full bg-gray-100 text-gray-600">Sem selo</span>
                                            : c.vigente ? <span className="text-xs px-2 py-0.5 rounded-full bg-green-100 text-green-700">Selando</span>
                                            : ! c.ativo ? <span className="text-xs px-2 py-0.5 rounded-full bg-gray-100 text-gray-600">Desativado</span>
                                            : <span className="text-xs px-2 py-0.5 rounded-full bg-red-100 text-red-700">Vencido</span>}
                                    </td>
                                    <td className="px-4 py-3 text-right whitespace-nowrap">
                                        <Button size="sm" variant="secondary" icon="fas fa-upload" onClick={() => setEnviandoPara(ug)}>
                                            {c ? 'Trocar' : 'Enviar certificado'}
                                        </Button>
                                        {c && (
                                            <button onClick={() => router.post(`/configuracoes/certificado-prefeitura/${ug.id}/toggle`, {}, { preserveScroll: true })}
                                                className="ml-2 text-xs text-gray-600 hover:underline">
                                                {c.ativo ? 'Desativar' : 'Reativar'}
                                            </button>
                                        )}
                                    </td>
                                </tr>
                            );
                        })}
                    </tbody>
                </table>
            </Card>

            {enviandoPara && <FormEnvio ug={enviandoPara} onFechar={() => setEnviandoPara(null)} />}
        </AdminLayout>
    );
}

function FormEnvio({ ug, onFechar }) {
    const { data, setData, post, processing, errors } = useForm({ ug_id: ug.id, pfx: null, senha: '' });

    const enviar = (e) => {
        e.preventDefault();
        post('/configuracoes/certificado-prefeitura', { forceFormData: true, onSuccess: onFechar });
    };

    return (
        <Modal show onClose={onFechar} title={`Certificado — ${ug.nome}`}>
            <form onSubmit={enviar} className="space-y-3">
                <div>
                    <label className="text-xs font-medium text-gray-600 block mb-1">Arquivo do e-CNPJ A1 (.pfx ou .p12)</label>
                    <input type="file" accept=".pfx,.p12" onChange={(e) => setData('pfx', e.target.files[0])} className="ds-input !h-auto py-1.5" required />
                    {errors.pfx && <p className="text-xs text-red-600 mt-1">{errors.pfx}</p>}
                </div>
                <div>
                    <label className="text-xs font-medium text-gray-600 block mb-1">Senha do certificado</label>
                    <input type="password" value={data.senha} onChange={(e) => setData('senha', e.target.value)} className="ds-input" required autoComplete="off" />
                    {errors.senha && <p className="text-xs text-red-600 mt-1">{errors.senha}</p>}
                </div>
                <p className="text-xs text-gray-500">A senha fica guardada cifrada para o sistema selar os documentos sem pedir de novo.</p>
                <div className="flex justify-end gap-2">
                    <Button variant="ghost" type="button" onClick={onFechar}>Cancelar</Button>
                    <Button type="submit" icon="fas fa-shield-alt" loading={processing} disabled={! data.pfx || ! data.senha}>Gravar certificado</Button>
                </div>
            </form>
        </Modal>
    );
}

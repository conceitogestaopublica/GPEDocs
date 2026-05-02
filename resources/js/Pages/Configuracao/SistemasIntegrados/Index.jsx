/**
 * Sistemas Integrados — admin de tokens de API para sistemas externos
 * (GPE, RH, Patrimonio, etc) que enviam documentos para assinatura digital.
 */
import { Head, Link, router, useForm, usePage } from '@inertiajs/react';
import { useEffect, useState } from 'react';
import AdminLayout from '../../../Layouts/AdminLayout';
import PageHeader from '../../../Components/PageHeader';
import Button from '../../../Components/Button';
import Card from '../../../Components/Card';
import Modal from '../../../Components/Modal';

export default function SistemasIntegradosIndex({ sistemas = [] }) {
    const { props } = usePage();
    const tokenGerado = props.flash?.token_gerado;

    const [showNovo, setShowNovo] = useState(false);
    const [tokenExibido, setTokenExibido] = useState(tokenGerado || null);
    const [editando, setEditando] = useState(null);
    const [confirmRegenerar, setConfirmRegenerar] = useState(null);

    useEffect(() => {
        if (tokenGerado) setTokenExibido(tokenGerado);
    }, [tokenGerado]);

    const baseUrl = (typeof window !== 'undefined' ? window.location.origin : '');

    return (
        <AdminLayout>
            <Head title="Sistemas Integrados" />

            <PageHeader title="Sistemas Integrados"
                subtitle="API REST para sistemas externos (GPE, RH, etc) enviarem documentos para assinatura">
                <Button icon="fas fa-plus" onClick={() => setShowNovo(true)}>Novo Sistema</Button>
            </PageHeader>

            <Card padding={false}>
                {sistemas.length === 0 ? (
                    <div className="py-16 text-center text-gray-400">
                        <i className="fas fa-plug text-4xl mb-3 block" />
                        <p className="text-sm font-medium">Nenhum sistema integrado cadastrado</p>
                        <p className="text-xs mt-1">Cadastre um sistema para gerar API token e habilitar a integracao.</p>
                    </div>
                ) : (
                    <div className="overflow-x-auto">
                        <table className="w-full text-sm">
                            <thead className="bg-gray-50 text-gray-500 uppercase text-[10px]">
                                <tr>
                                    <th className="px-3 py-2.5 text-left font-semibold">Codigo</th>
                                    <th className="px-3 py-2.5 text-left font-semibold">Nome</th>
                                    <th className="px-3 py-2.5 text-left font-semibold">Token</th>
                                    <th className="px-3 py-2.5 text-left font-semibold">Documentos</th>
                                    <th className="px-3 py-2.5 text-left font-semibold">Ultimo uso</th>
                                    <th className="px-3 py-2.5 text-left font-semibold">Status</th>
                                    <th className="px-3 py-2.5 text-center font-semibold w-44">Acoes</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-gray-100">
                                {sistemas.map(s => (
                                    <tr key={s.id} className={`hover:bg-gray-50 ${! s.ativo ? 'opacity-60' : ''}`}>
                                        <td className="px-3 py-2.5">
                                            <code className="text-xs bg-gray-100 px-1.5 py-0.5 rounded text-indigo-700">{s.codigo}</code>
                                        </td>
                                        <td className="px-3 py-2.5">
                                            <p className="font-medium text-gray-800">{s.nome}</p>
                                            {s.descricao && <p className="text-[10px] text-gray-500">{s.descricao}</p>}
                                        </td>
                                        <td className="px-3 py-2.5 font-mono text-[11px] text-gray-500">{s.token_mascarado}</td>
                                        <td className="px-3 py-2.5 text-xs">{s.total_documentos || 0}</td>
                                        <td className="px-3 py-2.5 text-[11px] text-gray-400 whitespace-nowrap">
                                            {s.ultimo_uso_em ? new Date(s.ultimo_uso_em).toLocaleString('pt-BR', { day: '2-digit', month: '2-digit', year: '2-digit', hour: '2-digit', minute: '2-digit' }) : '—'}
                                        </td>
                                        <td className="px-3 py-2.5">
                                            {s.ativo ? (
                                                <span className="text-[10px] px-2 py-0.5 rounded-full bg-green-100 text-green-700 font-bold">Ativo</span>
                                            ) : (
                                                <span className="text-[10px] px-2 py-0.5 rounded-full bg-gray-100 text-gray-600 font-bold">Inativo</span>
                                            )}
                                        </td>
                                        <td className="px-3 py-2.5">
                                            <div className="flex items-center justify-end gap-1.5">
                                                <button onClick={() => setEditando(s)} title="Editar"
                                                    className="text-[11px] px-2 py-1 rounded bg-white border border-gray-200 text-gray-600 hover:bg-gray-50">
                                                    <i className="fas fa-pen" />
                                                </button>
                                                <button onClick={() => setConfirmRegenerar({ ...s, tipo: 'token' })} title="Regenerar API token"
                                                    className="text-[11px] px-2 py-1 rounded bg-white border border-amber-300 text-amber-700 hover:bg-amber-50">
                                                    <i className="fas fa-sync" />
                                                </button>
                                                <button onClick={() => setConfirmRegenerar({ ...s, tipo: 'secret' })} title="Regenerar webhook secret"
                                                    className="text-[11px] px-2 py-1 rounded bg-white border border-purple-300 text-purple-700 hover:bg-purple-50">
                                                    <i className="fas fa-shield-alt" />
                                                </button>
                                                <button onClick={() => router.post(`/configuracoes/sistemas-integrados/${s.id}/toggle-ativo`)}
                                                    title={s.ativo ? 'Desativar' : 'Reativar'}
                                                    className={`text-[11px] px-2 py-1 rounded bg-white border ${s.ativo ? 'border-orange-300 text-orange-700 hover:bg-orange-50' : 'border-green-300 text-green-700 hover:bg-green-50'}`}>
                                                    <i className={`fas fa-${s.ativo ? 'eye-slash' : 'eye'}`} />
                                                </button>
                                                <button onClick={() => {
                                                        if (confirm('Excluir este sistema? Apenas se nao tiver documentos enviados.')) {
                                                            router.delete(`/configuracoes/sistemas-integrados/${s.id}`);
                                                        }
                                                    }} title="Excluir"
                                                    className="text-[11px] px-2 py-1 rounded bg-white border border-red-300 text-red-600 hover:bg-red-50">
                                                    <i className="fas fa-trash" />
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                )}
            </Card>

            <div className="mt-4 bg-blue-50 border border-blue-200 rounded-xl p-4">
                <p className="text-xs text-blue-800 font-semibold mb-1">
                    <i className="fas fa-book mr-1" />Como integrar um sistema externo
                </p>
                <p className="text-[11px] text-blue-700 mb-2">
                    Os sistemas cadastrados podem enviar documentos para assinatura via:
                </p>
                <code className="block bg-white border border-blue-200 rounded p-2 text-[11px] text-gray-700 mb-2">
                    POST {baseUrl}/api/integracoes/documentos<br />
                    Header: Authorization: Bearer {'{token}'}
                </code>
                <p className="text-[11px] text-blue-700">
                    Documentacao completa: <a href="/docs/integracao-externa.md" target="_blank" className="underline">docs/integracao-externa.md</a>
                </p>
            </div>

            {/* Modal: Token gerado (uma unica exibicao) */}
            {tokenExibido && (
                <Modal show={!! tokenExibido} onClose={() => setTokenExibido(null)} title="Credenciais geradas — copie agora!">
                    <div className="space-y-4">
                        <div className="bg-amber-50 border border-amber-300 rounded-xl p-3">
                            <p className="text-xs text-amber-800 font-semibold mb-1">
                                <i className="fas fa-exclamation-triangle mr-1" />
                                Estas credenciais sao exibidas APENAS uma vez
                            </p>
                            <p className="text-[11px] text-amber-700">
                                Copie e guarde em local seguro. Para gerar novas, use os botoes "Regenerar".
                            </p>
                        </div>

                        {tokenExibido.token && (
                            <div>
                                <label className="block text-xs font-medium text-gray-700 mb-1">
                                    <i className="fas fa-key mr-1" />
                                    API Token do sistema <code className="bg-gray-100 px-1 rounded">{tokenExibido.codigo}</code>
                                </label>
                                <div className="flex items-stretch gap-2">
                                    <input type="text" readOnly value={tokenExibido.token}
                                        onClick={(e) => e.target.select()}
                                        className="ds-input font-mono text-xs flex-1" />
                                    <Button type="button" icon="fas fa-copy" onClick={() => {
                                        navigator.clipboard?.writeText(tokenExibido.token);
                                        alert('Token copiado!');
                                    }}>Copiar</Button>
                                </div>
                                <p className="mt-1 text-[10px] text-gray-500">
                                    Use no header: <code>Authorization: Bearer {tokenExibido.token.substring(0, 12)}...</code>
                                </p>
                            </div>
                        )}

                        {tokenExibido.webhook_secret && (
                            <div>
                                <label className="block text-xs font-medium text-gray-700 mb-1">
                                    <i className="fas fa-shield-alt mr-1" />
                                    Webhook Secret <span className="text-gray-400 text-[10px]">(HMAC-SHA256)</span>
                                </label>
                                <div className="flex items-stretch gap-2">
                                    <input type="text" readOnly value={tokenExibido.webhook_secret}
                                        onClick={(e) => e.target.select()}
                                        className="ds-input font-mono text-xs flex-1" />
                                    <Button type="button" icon="fas fa-copy" onClick={() => {
                                        navigator.clipboard?.writeText(tokenExibido.webhook_secret);
                                        alert('Webhook secret copiado!');
                                    }}>Copiar</Button>
                                </div>
                                <p className="mt-1 text-[10px] text-gray-500">
                                    Cada webhook que o GPE Docs enviar tera o header <code>X-GpeDocs-Signature: sha256=&lt;hmac&gt;</code>.
                                    Use este secret pra validar a autenticidade do payload.
                                </p>
                            </div>
                        )}

                        <div className="flex justify-end">
                            <Button variant="secondary" onClick={() => setTokenExibido(null)}>Fechei e guardei</Button>
                        </div>
                    </div>
                </Modal>
            )}

            {/* Modal: Novo sistema */}
            {showNovo && <NovoSistemaModal onClose={() => setShowNovo(false)} />}

            {/* Modal: Editar */}
            {editando && <EditarSistemaModal sistema={editando} onClose={() => setEditando(null)} />}

            {/* Modal: Confirmar regenerar token/secret */}
            {confirmRegenerar && (
                <Modal show={!! confirmRegenerar} onClose={() => setConfirmRegenerar(null)}
                    title={confirmRegenerar.tipo === 'secret' ? 'Regenerar webhook secret' : 'Regenerar API token'}>
                    <div className="space-y-4">
                        <div className="bg-red-50 border border-red-200 rounded-xl p-3 text-xs text-red-800">
                            <i className="fas fa-exclamation-triangle mr-1" />
                            {confirmRegenerar.tipo === 'secret' ? (
                                <>O webhook secret atual sera <strong>invalidado imediatamente</strong>. Webhooks
                                enviados depois disso virao com nova assinatura — atualize o validador no client.</>
                            ) : (
                                <>O API token atual sera <strong>invalidado imediatamente</strong>. Qualquer integracao
                                que use o token antigo vai parar de funcionar ate ser atualizada.</>
                            )}
                        </div>
                        <p className="text-sm text-gray-700">
                            Confirmar regeneracao{confirmRegenerar.tipo === 'secret' ? ' do webhook secret' : ' do API token'} de <code className="bg-gray-100 px-1 rounded">{confirmRegenerar.codigo}</code>?
                        </p>
                        <div className="flex justify-end gap-2">
                            <Button variant="secondary" onClick={() => setConfirmRegenerar(null)}>Cancelar</Button>
                            <Button variant="danger"
                                icon={confirmRegenerar.tipo === 'secret' ? 'fas fa-shield-alt' : 'fas fa-sync'}
                                onClick={() => {
                                    const url = confirmRegenerar.tipo === 'secret'
                                        ? `/configuracoes/sistemas-integrados/${confirmRegenerar.id}/regenerar-webhook-secret`
                                        : `/configuracoes/sistemas-integrados/${confirmRegenerar.id}/regenerar-token`;
                                    router.post(url, {}, { onSuccess: () => setConfirmRegenerar(null) });
                                }}>Regenerar</Button>
                        </div>
                    </div>
                </Modal>
            )}
        </AdminLayout>
    );
}

function NovoSistemaModal({ onClose }) {
    const { data, setData, post, processing, errors } = useForm({
        codigo: '', nome: '', descricao: '',
    });

    const submit = (e) => {
        e.preventDefault();
        post('/configuracoes/sistemas-integrados', {
            preserveScroll: true,
            onSuccess: () => onClose(),
        });
    };

    return (
        <Modal show={true} onClose={onClose} title="Novo Sistema Integrado">
            <form onSubmit={submit} className="space-y-4">
                <div>
                    <label className="block text-sm font-medium text-gray-700 mb-1">
                        Codigo <span className="text-red-500">*</span>
                    </label>
                    <input type="text" value={data.codigo}
                        onChange={(e) => setData('codigo', e.target.value.toLowerCase().replace(/[^a-z0-9_-]/g, ''))}
                        className="ds-input font-mono"
                        placeholder="gpe, rh, patrimonio..."
                        maxLength={50} autoFocus />
                    <p className="mt-1 text-[10px] text-gray-400">
                        So letras minusculas, numeros, hifen e underscore. Sera usado em logs e identificacao.
                    </p>
                    {errors.codigo && <p className="mt-1 text-xs text-red-600">{errors.codigo}</p>}
                </div>

                <div>
                    <label className="block text-sm font-medium text-gray-700 mb-1">
                        Nome amigavel <span className="text-red-500">*</span>
                    </label>
                    <input type="text" value={data.nome}
                        onChange={(e) => setData('nome', e.target.value)}
                        className="ds-input"
                        placeholder='Ex: GPE - Sistema de Gestao Publica' />
                    {errors.nome && <p className="mt-1 text-xs text-red-600">{errors.nome}</p>}
                </div>

                <div>
                    <label className="block text-sm font-medium text-gray-700 mb-1">Descricao</label>
                    <textarea value={data.descricao}
                        onChange={(e) => setData('descricao', e.target.value)}
                        rows={3} className="ds-input !h-auto"
                        placeholder="Tipos de documentos enviados, contato, etc..." />
                </div>

                <div className="bg-blue-50 border border-blue-200 rounded-xl p-3 text-[11px] text-blue-800">
                    <i className="fas fa-info-circle mr-1" />
                    Apos criar, um <strong>token unico</strong> sera gerado e exibido <strong>uma unica vez</strong>.
                    Copie e use no header HTTP <code className="bg-white px-1 rounded">Authorization: Bearer ...</code>
                </div>

                <div className="flex justify-end gap-2 pt-2 border-t border-gray-100">
                    <Button variant="secondary" type="button" onClick={onClose}>Cancelar</Button>
                    <Button type="submit" loading={processing} icon="fas fa-plus">Cadastrar e gerar token</Button>
                </div>
            </form>
        </Modal>
    );
}

function EditarSistemaModal({ sistema, onClose }) {
    const { data, setData, put, processing, errors } = useForm({
        nome: sistema.nome,
        descricao: sistema.descricao || '',
    });

    const submit = (e) => {
        e.preventDefault();
        put(`/configuracoes/sistemas-integrados/${sistema.id}`, { onSuccess: onClose });
    };

    return (
        <Modal show={true} onClose={onClose} title={`Editar ${sistema.codigo}`}>
            <form onSubmit={submit} className="space-y-4">
                <div>
                    <label className="block text-sm font-medium text-gray-700 mb-1">Codigo</label>
                    <input type="text" value={sistema.codigo} readOnly disabled
                        className="ds-input font-mono bg-gray-50 cursor-not-allowed" />
                    <p className="mt-1 text-[10px] text-gray-400">Codigo nao pode ser alterado.</p>
                </div>
                <div>
                    <label className="block text-sm font-medium text-gray-700 mb-1">Nome <span className="text-red-500">*</span></label>
                    <input type="text" value={data.nome}
                        onChange={(e) => setData('nome', e.target.value)}
                        className="ds-input" />
                    {errors.nome && <p className="mt-1 text-xs text-red-600">{errors.nome}</p>}
                </div>
                <div>
                    <label className="block text-sm font-medium text-gray-700 mb-1">Descricao</label>
                    <textarea value={data.descricao}
                        onChange={(e) => setData('descricao', e.target.value)}
                        rows={3} className="ds-input !h-auto" />
                </div>
                <div className="flex justify-end gap-2 pt-2 border-t border-gray-100">
                    <Button variant="secondary" type="button" onClick={onClose}>Cancelar</Button>
                    <Button type="submit" loading={processing} icon="fas fa-save">Salvar</Button>
                </div>
            </form>
        </Modal>
    );
}

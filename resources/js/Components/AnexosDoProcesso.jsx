/**
 * Anexos do processo (TR 4.14.5, 4.20.2–4.20.6, 4.38.24–4.38.26): ordem, aprovar/reprovar
 * com motivo, downloads, pré-visualização (PDF e imagem), galeria de imagens, ZIP de
 * todos e link seguro com validade.
 */
import { useState } from 'react';
import { Link, router } from '@inertiajs/react';
import Card from './Card';
import Button from './Button';
import Modal from './Modal';

const ehImagem = (a) => (a.mime_type || '').startsWith('image/');
const ehPdf = (a) => (a.mime_type || '').includes('pdf');
const tamanho = (b) => !b ? '' : b < 1024 ? `${b} B` : b < 1048576 ? `${(b / 1024).toFixed(1)} KB` : `${(b / 1048576).toFixed(1)} MB`;
const icone = (a) => ehPdf(a) ? 'fas fa-file-pdf text-red-500' : ehImagem(a) ? 'fas fa-file-image text-violet-500'
    : /word|document/.test(a.mime_type || '') ? 'fas fa-file-word text-blue-500' : /sheet|excel/.test(a.mime_type || '') ? 'fas fa-file-excel text-green-600' : 'fas fa-file text-gray-400';
const url = (a, inline) => `/processos/anexos/${a.id}/download${inline ? '?inline=1' : ''}`;

export default function AnexosDoProcesso({ processoId, anexos = [], podeOrganizar = false, abrirDireto = false, requerenteEmail = null }) {
    const [copia, setCopia] = useState(null);         // { anexo, email, mensagem } — cópia por e-mail (TR 4.49.8)
    const [vendo, setVendo] = useState(null);          // anexo na pré-visualização
    const [avaliando, setAvaliando] = useState(null);  // { anexo, avaliacao }
    const [motivo, setMotivo] = useState('');
    const [link, setLink] = useState(null);            // { anexo, horas, url, valido_ate }
    const imagens = anexos.filter(ehImagem);

    const mover = (idx, delta) => {
        const ids = anexos.map(a => a.id);
        const [m] = ids.splice(idx, 1);
        ids.splice(idx + delta, 0, m);
        router.post(`/processos/${processoId}/anexos/ordem`, { ids }, { preserveScroll: true });
    };
    const avaliar = (anexo, avaliacao, mot = null) => router.post(`/processos/anexos/${anexo.id}/avaliar`, { avaliacao, motivo: mot },
        { preserveScroll: true, onSuccess: () => { setAvaliando(null); setMotivo(''); } });
    const gerarLink = async () => {
        const xsrf = decodeURIComponent((document.cookie.match(/XSRF-TOKEN=([^;]+)/) || [])[1] || '');
        const r = await fetch(`/processos/anexos/${link.anexo.id}/link`, { method: 'POST', headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-XSRF-TOKEN': xsrf }, body: JSON.stringify({ horas: link.horas }) });
        const j = await r.json();
        setLink({ ...link, url: j.url, valido_ate: j.valido_ate });
    };

    return (
        <Card title={`Anexos (${anexos.length})`}>
            {anexos.length > 0 && (
                <div className="flex flex-wrap gap-2 mb-3">
                    <a href={`/processos/${processoId}/anexos/zip`} className="ds-btn ds-btn-outline ds-btn-sm"><i className="fas fa-file-zipper mr-1" />Baixar todos (ZIP)</a>
                    {imagens.length > 1 && (
                        <button type="button" className="ds-btn ds-btn-outline ds-btn-sm" onClick={() => setVendo(imagens[0])}><i className="fas fa-images mr-1" />Galeria ({imagens.length})</button>
                    )}
                </div>
            )}
            {anexos.length === 0 ? (
                <p className="text-sm text-gray-400">Nenhum anexo</p>
            ) : (
                <div className="space-y-2">
                    {anexos.map((a, idx) => (
                        <div key={a.id} className={`rounded-lg px-3 py-2.5 border ${a.avaliacao === 'reprovado' ? 'bg-red-50 border-red-100' : a.avaliacao === 'aprovado' ? 'bg-green-50 border-green-100' : 'bg-gray-50 border-transparent'}`}>
                            <div className="flex items-center justify-between gap-3">
                                <div className="flex items-center gap-3 min-w-0">
                                    {podeOrganizar && anexos.length > 1 && (
                                        <div className="flex flex-col">
                                            <button type="button" disabled={idx === 0} onClick={() => mover(idx, -1)} title="Subir" className="text-gray-400 hover:text-gray-700 disabled:opacity-20 leading-none"><i className="fas fa-chevron-up text-[10px]" /></button>
                                            <button type="button" disabled={idx === anexos.length - 1} onClick={() => mover(idx, 1)} title="Descer" className="text-gray-400 hover:text-gray-700 disabled:opacity-20 leading-none"><i className="fas fa-chevron-down text-[10px]" /></button>
                                        </div>
                                    )}
                                    {ehImagem(a)
                                        ? <img src={url(a, true)} alt="" className="w-10 h-10 object-cover rounded border border-gray-200 cursor-pointer" onClick={() => setVendo(a)} />
                                        : <i className={`${icone(a)} text-lg w-10 text-center`} />}
                                    <div className="min-w-0">
                                        <p className="text-sm font-medium text-gray-700 truncate">{a.nome}</p>
                                        <p className="text-xs text-gray-400">
                                            {tamanho(a.tamanho)}{a.downloads > 0 && <> · <i className="fas fa-download text-[9px]" /> {a.downloads} download(s)</>}
                                            {a.avaliacao && <> · <span className={a.avaliacao === 'aprovado' ? 'text-green-700' : 'text-red-700'}>{a.avaliacao === 'aprovado' ? 'Aprovado' : 'Reprovado'} por {a.avaliador?.name || '-'}</span></>}
                                        </p>
                                        {a.avaliacao === 'reprovado' && a.avaliacao_motivo && <p className="text-xs text-red-700">Motivo: {a.avaliacao_motivo}</p>}
                                    </div>
                                </div>
                                <div className="flex items-center gap-3 shrink-0 text-xs">
                                    {(ehPdf(a) || ehImagem(a)) && <button type="button" className="text-gray-600 hover:text-blue-700" title="Ver" onClick={() => setVendo(a)}><i className="fas fa-eye" /></button>}
                                    {a.documento_id ? (
                                        <Link href={`/documentos/${a.documento_id}?aba=assinaturas`} className="text-emerald-700 hover:underline"><i className="fas fa-file-signature mr-1" />Assinaturas</Link>
                                    ) : ehPdf(a) && (
                                        <button type="button" onClick={() => router.post(`/processos/anexos/${a.id}/enviar-assinatura`)} className="text-blue-600 hover:underline"
                                            title="Arquiva o anexo no GED e escolhe quem assina"><i className="fas fa-pen-nib mr-1" />Assinar</button>
                                    )}
                                    {/* O que o externo vê deste anexo (TR 4.48.20) */}
                                    {podeOrganizar && (
                                        <select value={a.externo || 'nao'} title="Visibilidade para o requerente e participantes" className="text-[11px] border border-gray-200 rounded px-1 py-0.5 text-gray-600"
                                            onChange={(e) => router.post(`/processos/anexos/${a.id}/externo`, { externo: e.target.value }, { preserveScroll: true })}>
                                            <option value="nao">só interno</option><option value="sempre">externo vê</option><option value="ao_deferir">externo vê após deferir</option>
                                        </select>
                                    )}
                                    {! podeOrganizar && a.externo && a.externo !== 'nao' && <span className="text-[11px] text-gray-500"><i className="fas fa-eye mr-1" />{a.externo === 'sempre' ? 'externo vê' : 'após deferir'}</span>}
                                    {/* Revisão com anotações e medição (TR 4.48.16, 4.48.39) */}
                                    {ehPdf(a) && <Link href={`/processos/anexos/${a.id}/revisar`} className="text-violet-700 hover:underline" title="Anotar, medir e aceitar/recusar"><i className="fas fa-pen-ruler mr-1" />Revisar</Link>}
                                    {podeOrganizar && (
                                        <>
                                            {a.avaliacao !== 'aprovado' && <button type="button" className="text-green-700 hover:underline" onClick={() => avaliar(a, 'aprovado')}>Aprovar</button>}
                                            {a.avaliacao !== 'reprovado' && <button type="button" className="text-red-700 hover:underline" onClick={() => setAvaliando({ anexo: a })}>Reprovar</button>}
                                            {a.avaliacao && <button type="button" className="text-gray-500 hover:underline" onClick={() => avaliar(a, 'pendente')} title="Retirar a avaliação">Desfazer</button>}
                                        </>
                                    )}
                                    <button type="button" className="text-gray-600 hover:text-blue-700" title="Enviar cópia por e-mail ao requerente ou a terceiro" onClick={() => setCopia({ anexo: a, email: requerenteEmail || '', mensagem: '' })}><i className="fas fa-envelope" /></button>
                                    <button type="button" className="text-gray-600 hover:text-blue-700" title="Link seguro com validade" onClick={() => setLink({ anexo: a, horas: 24 })}><i className="fas fa-link" /></button>
                                    <a href={url(a, abrirDireto)} target="_blank" rel="noopener noreferrer" className="text-blue-600 hover:text-blue-800" title="Baixar"><i className="fas fa-download" /></a>
                                </div>
                            </div>
                        </div>
                    ))}
                </div>
            )}

            {/* Pré-visualização / galeria (TR 4.20.5, 4.20.6) */}
            <Modal show={!! vendo} onClose={() => setVendo(null)} title={vendo?.nome || ''} maxWidth="4xl">
                {vendo && (ehPdf(vendo)
                    ? <iframe src={url(vendo, true)} title={vendo.nome} className="w-full h-[75vh] rounded border border-gray-200" />
                    : (
                        <div>
                            <div className="flex items-center justify-center bg-gray-900 rounded-lg min-h-[50vh]">
                                <img src={url(vendo, true)} alt={vendo.nome} className="max-h-[70vh] max-w-full object-contain" />
                            </div>
                            {imagens.length > 1 && (
                                <div className="flex gap-2 mt-3 overflow-x-auto">
                                    {imagens.map(im => (
                                        <img key={im.id} src={url(im, true)} alt="" loading="lazy" onClick={() => setVendo(im)}
                                            className={`h-16 w-16 object-cover rounded cursor-pointer border-2 ${im.id === vendo.id ? 'border-cyan-500' : 'border-transparent'}`} />
                                    ))}
                                </div>
                            )}
                        </div>
                    ))}
            </Modal>

            {/* Reprovar com motivo (TR 4.38.26) */}
            <Modal show={!! avaliando} onClose={() => setAvaliando(null)} title="Reprovar anexo">
                {avaliando && (
                    <div className="space-y-3">
                        <p className="text-sm text-gray-600">{avaliando.anexo.nome}</p>
                        <textarea value={motivo} onChange={(e) => setMotivo(e.target.value)} rows={3} className="ds-input !h-auto" placeholder="Motivo da reprovação (quem enviou é avisado)" />
                        <div className="flex justify-end gap-2">
                            <Button variant="secondary" onClick={() => setAvaliando(null)}>Cancelar</Button>
                            <Button variant="danger" disabled={! motivo.trim()} onClick={() => avaliar(avaliando.anexo, 'reprovado', motivo)}>Reprovar</Button>
                        </div>
                    </div>
                )}
            </Modal>

            {/* Link seguro com validade (TR 4.20.2) */}
            <Modal show={!! link} onClose={() => setLink(null)} title="Link seguro do anexo">
                {link && (
                    <div className="space-y-3">
                        <p className="text-sm text-gray-600">Quem tiver o link baixa <b>{link.anexo.nome}</b> sem entrar no sistema, só até a validade. Fica registrado no processo.</p>
                        {! link.url ? (
                            <div className="flex items-end gap-2">
                                <div>
                                    <label className="block text-sm font-medium text-gray-700 mb-1">Validade</label>
                                    <select value={link.horas} onChange={(e) => setLink({ ...link, horas: Number(e.target.value) })} className="ds-input">
                                        {[[1, '1 hora'], [24, '24 horas'], [72, '3 dias'], [168, '7 dias']].map(([h, r]) => <option key={h} value={h}>{r}</option>)}
                                    </select>
                                </div>
                                <Button variant="primary" icon="fas fa-link" onClick={gerarLink}>Gerar link</Button>
                            </div>
                        ) : (
                            <div className="space-y-2">
                                <input readOnly value={link.url} className="ds-input text-xs font-mono" onFocus={(e) => e.target.select()} />
                                <p className="text-xs text-gray-500">Vale até {new Date(link.valido_ate).toLocaleString('pt-BR')}.</p>
                                <Button variant="secondary" icon="fas fa-copy" onClick={() => navigator.clipboard?.writeText(link.url)}>Copiar</Button>
                            </div>
                        )}
                    </div>
                )}
            </Modal>
            {/* Cópia do anexo por e-mail ao requerente ou a terceiro (TR 4.49.8) */}
            <Modal show={!! copia} onClose={() => setCopia(null)} title="Enviar cópia por e-mail">
                {copia && (
                    <div className="space-y-3">
                        <p className="text-sm text-gray-600">Arquivo: <b>{copia.anexo.nome}</b></p>
                        <label className="block text-sm text-gray-700">Para<input type="email" value={copia.email} onChange={(e) => setCopia({ ...copia, email: e.target.value })} className="ds-input mt-1" placeholder="e-mail do requerente ou de terceiro" /></label>
                        <textarea value={copia.mensagem} onChange={(e) => setCopia({ ...copia, mensagem: e.target.value })} rows={3} className="ds-input !h-auto" placeholder="Mensagem (opcional)" />
                        <div className="flex justify-end gap-2">
                            <Button variant="secondary" onClick={() => setCopia(null)}>Cancelar</Button>
                            <Button icon="fas fa-paper-plane" disabled={! copia.email.trim()} onClick={() => router.post(`/processos/anexos/${copia.anexo.id}/copia`, { email: copia.email, mensagem: copia.mensagem }, { preserveScroll: true, onSuccess: () => setCopia(null) })}>Enviar</Button>
                        </div>
                    </div>
                )}
            </Modal>
        </Card>
    );
}

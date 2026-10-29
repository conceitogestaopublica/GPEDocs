/**
 * Configurações › Identidade visual (TR 4.22.1): cor predominante do sistema e do portal,
 * brasão mostrado na tela de acesso e imagem de fundo dessa tela. A cor é aplicada na
 * prévia enquanto se escolhe; salvar vale para todos.
 */
import { useEffect, useState } from 'react';
import { Head, useForm } from '@inertiajs/react';
import AdminLayout from '../../Layouts/AdminLayout';
import PageHeader from '../../Components/PageHeader';
import Card from '../../Components/Card';
import Button from '../../Components/Button';

const SUGESTOES = ['#0891b2', '#1d4ed8', '#047857', '#15803d', '#b91c1c', '#9a3412', '#a16207', '#6d28d9', '#be185d', '#334155'];
const TONS = { 50: ['white', 8], 100: ['white', 16], 200: ['white', 30], 300: ['white', 50], 400: ['white', 75], 500: [null, 100], 600: ['black', 85], 700: ['black', 70], 800: ['black', 55], 900: ['black', 42], 950: ['black', 30] };

/** Mesma paleta do servidor (IdentidadeVisual::css), para ver antes de salvar. */
function aplicarPrevia(cor) {
    let el = document.getElementById('identidade-previa');
    if (! el) { el = document.createElement('style'); el.id = 'identidade-previa'; document.head.appendChild(el); }
    const vars = Object.entries(TONS).flatMap(([t, [com, pct]]) => ['primary', 'cyan', 'blue']
        .map((p) => `--color-${p}-${t}: ${com ? `color-mix(in oklab, ${cor} ${pct}%, ${com})` : cor};`));
    el.textContent = `:root{${vars.join('')}}`;
}

export default function IdentidadeVisual({ identidade, ugs = [], padrao }) {
    const form = useForm({ cor: identidade.cor, brasao_ug_id: identidade.brasao_ug_id || '', fundo: null, remover_fundo: false });
    const [fundoPrevia, setFundoPrevia] = useState(identidade.fundo_url);

    useEffect(() => {
        if (/^#[0-9a-f]{6}$/i.test(form.data.cor)) aplicarPrevia(form.data.cor);
    }, [form.data.cor]);
    // Ao sair sem salvar, a prévia some (a cor salva continua no <style> do servidor)
    useEffect(() => () => document.getElementById('identidade-previa')?.remove(), []);

    const escolherFundo = (arquivo) => {
        form.setData((d) => ({ ...d, fundo: arquivo, remover_fundo: false }));
        setFundoPrevia(arquivo ? URL.createObjectURL(arquivo) : identidade.fundo_url);
    };
    const salvar = (e) => {
        e.preventDefault();
        form.post('/configuracoes/identidade-visual', { forceFormData: true, preserveScroll: true, onSuccess: () => form.setData('fundo', null) });
    };
    const ugBrasao = ugs.find((u) => String(u.id) === String(form.data.brasao_ug_id));

    return (
        <AdminLayout>
            <Head title="Identidade visual" />
            <PageHeader title="Identidade visual" subtitle="Cor, brasão e fundo da tela de acesso do município — vale para o sistema e o portal" />
            <form onSubmit={salvar} className="grid grid-cols-1 lg:grid-cols-2 gap-6">
                <div className="space-y-6">
                    <Card title="Cor predominante">
                        <div className="flex items-center gap-3">
                            <input type="color" value={form.data.cor} onChange={(e) => form.setData('cor', e.target.value)} className="w-14 h-10 rounded border border-gray-200 cursor-pointer" aria-label="Escolher cor" />
                            <input type="text" value={form.data.cor} onChange={(e) => form.setData('cor', e.target.value)} className="ds-input w-32 font-mono" maxLength={7} />
                            <button type="button" onClick={() => form.setData('cor', padrao)} className="text-xs text-gray-500 hover:underline">Voltar ao padrão</button>
                        </div>
                        {form.errors.cor && <p className="mt-1 text-xs text-red-600">{form.errors.cor}</p>}
                        <div className="mt-3 flex flex-wrap gap-2">
                            {SUGESTOES.map((c) => (
                                <button key={c} type="button" onClick={() => form.setData('cor', c)} title={c} aria-label={`Cor ${c}`}
                                    className={`w-8 h-8 rounded-full border-2 ${form.data.cor === c ? 'border-gray-800' : 'border-white shadow'}`} style={{ background: c }} />
                            ))}
                        </div>
                        <p className="mt-3 text-[11px] text-gray-500">Botões, menu lateral, links e destaques passam a usar esta cor. Prefira cores escuras o bastante para texto branco por cima.</p>
                    </Card>

                    <Card title="Brasão na tela de acesso">
                        <select value={form.data.brasao_ug_id} onChange={(e) => form.setData('brasao_ug_id', e.target.value)} className="ds-input">
                            <option value="">Automático (primeira UG com brasão)</option>
                            {ugs.map((u) => <option key={u.id} value={u.id} disabled={! u.tem_brasao}>{u.nome}{u.tem_brasao ? '' : ' — sem brasão'}</option>)}
                        </select>
                        <p className="mt-2 text-[11px] text-gray-500">O brasão é enviado no cadastro da UG (Configurações › Unidades Gestoras).{ugBrasao && ! ugBrasao.tem_brasao ? ' Esta UG ainda não tem brasão.' : ''}</p>
                    </Card>

                    <Card title="Imagem de fundo da tela de acesso">
                        <input type="file" accept="image/png,image/jpeg,image/webp" onChange={(e) => escolherFundo(e.target.files?.[0] || null)} className="block text-sm" />
                        {form.errors.fundo && <p className="mt-1 text-xs text-red-600">{form.errors.fundo}</p>}
                        {identidade.fundo_url && ! form.data.fundo && (
                            <label className="mt-2 flex items-center gap-2 text-xs text-gray-600">
                                <input type="checkbox" checked={form.data.remover_fundo} onChange={(e) => { form.setData('remover_fundo', e.target.checked); setFundoPrevia(e.target.checked ? null : identidade.fundo_url); }} />
                                Remover a imagem atual
                            </label>
                        )}
                        <p className="mt-2 text-[11px] text-gray-500">Foto da cidade ou da prefeitura, na horizontal (até 5 MB). Uma camada clara por cima mantém o texto legível.</p>
                    </Card>

                    <div className="flex justify-end">
                        <Button type="submit" icon="fas fa-save" loading={form.processing}>Salvar identidade visual</Button>
                    </div>
                </div>

                <Card title="Prévia">
                    <div className="rounded-xl overflow-hidden border border-gray-200">
                        <div className="relative h-48 bg-cover bg-center flex items-center justify-center" style={fundoPrevia ? { backgroundImage: `url(${fundoPrevia})` } : { background: 'linear-gradient(135deg, var(--color-cyan-50), white, var(--color-blue-50))' }}>
                            {fundoPrevia && <div className="absolute inset-0 bg-white/75" />}
                            <div className="relative text-center">
                                {ugBrasao?.tem_brasao || (! form.data.brasao_ug_id && ugs.some((u) => u.tem_brasao))
                                    ? <img src={`/identidade/brasao?ug=${form.data.brasao_ug_id || ''}&t=${Date.now() % 1000}`} alt="" className="h-16 mx-auto mb-2" onError={(e) => { e.currentTarget.style.display = 'none'; }} />
                                    : null}
                                <p className="text-sm font-bold text-slate-700">Tela de acesso</p>
                            </div>
                        </div>
                        <div className="flex">
                            <div className="w-16 bg-primary-900 py-4 flex flex-col items-center gap-3">
                                <span className="w-10 h-8 rounded-lg bg-primary-500" /><span className="w-8 h-2 rounded bg-primary-700" /><span className="w-8 h-2 rounded bg-primary-700" />
                            </div>
                            <div className="flex-1 p-4 space-y-3 bg-gray-50">
                                <div className="flex gap-2">
                                    <span className="px-3 py-1.5 rounded-lg bg-cyan-600 text-white text-xs font-medium">Botão</span>
                                    <span className="px-3 py-1.5 rounded-lg bg-cyan-50 text-cyan-800 text-xs">Selecionado</span>
                                </div>
                                <p className="text-xs text-gray-600">Texto com <a className="text-cyan-700 underline">link</a> e um selo <span className="px-1.5 rounded bg-blue-100 text-blue-700">azul</span>.</p>
                            </div>
                        </div>
                    </div>
                    <p className="mt-2 text-[11px] text-gray-500">A cor já está aplicada nesta tela; só passa a valer para todos depois de salvar.</p>
                </Card>
            </form>
        </AdminLayout>
    );
}

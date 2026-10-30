/**
 * Ponto de entrada do editor de texto nas telas: o avançado é o EditorTexto (TipTap, TR 4.16);
 * o básico é texto simples, para quem escolheu assim em Meu perfil › Preferências.
 */
import { useState } from 'react';
import { usePage } from '@inertiajs/react';
import EditorTexto from './Editor/EditorTexto';

const TEM_FORMATACAO = /<(p|br|b|strong|i|em|u|ul|ol|li|table|h[1-6]|div|span)[\s>/]/i;

/**
 * Escolhe o editor pela preferência do servidor (Meu perfil › Preferências):
 * avançado (formatação) ou básico (texto simples — o servidor converte as
 * quebras de linha). Texto já formatado aberto no básico avisa antes de
 * perder a formatação e deixa usar o avançado naquela vez.
 */
export default function RichEditor(props) {
    const preferencia = usePage().props.auth?.user?.preferencias?.editor || 'avancado';
    const [avancadoNestaVez, setAvancadoNestaVez] = useState(false);

    if (preferencia !== 'basico' || avancadoNestaVez) return <EditorTexto {...props} />;

    return <EditorBasico {...props} onUsarAvancado={() => setAvancadoNestaVez(true)} />;
}

function EditorBasico({ html, onChange, minHeight = 350, placeholder = '', onUsarAvancado }) {
    const formatado = TEM_FORMATACAO.test(html || '');
    return (
        <div className="space-y-2">
            {formatado && (
                <div className="flex items-center justify-between gap-3 text-xs bg-amber-50 border border-amber-200 text-amber-800 rounded-md px-3 py-2">
                    <span><i className="fas fa-exclamation-triangle mr-1" />Este texto tem formatação; no editor básico ela se perde ao salvar.</span>
                    <button type="button" onClick={onUsarAvancado} className="font-semibold underline whitespace-nowrap">Usar o editor avançado</button>
                </div>
            )}
            <textarea value={formatado ? textoDe(html) : (html || '')} onChange={(e) => onChange(e.target.value)}
                placeholder={placeholder} style={{ minHeight }}
                className="w-full border border-gray-300 rounded-md p-3 text-sm leading-relaxed focus:outline-none focus:ring-2 focus:ring-cyan-200" />
        </div>
    );
}

/** Texto puro de um HTML, mantendo os parágrafos como linhas. */
function textoDe(html) {
    const div = document.createElement('div');
    div.innerHTML = String(html).replace(/<\/(p|div|li|h[1-6])>/gi, '\n').replace(/<br\s*\/?>/gi, '\n');
    return (div.textContent || '').replace(/\n{3,}/g, '\n\n').trim();
}

/** Texto puro (modelos antigos de despacho) em parágrafos para o editor; HTML passa como está. */
export function textoParaHtml(texto) {
    const t = String(texto || '');
    if (TEM_FORMATACAO.test(t)) return t;
    const esc = (s) => s.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
    return t.split(/\n{2,}/).map((par) => `<p>${esc(par).replace(/\n/g, '<br>')}</p>`).join('');
}

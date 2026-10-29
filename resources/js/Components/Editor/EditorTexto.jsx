/**
 * Editor de texto único do GED (TR 4.16, 4.45.3, 4.46.2), sobre TipTap: formatação,
 * tamanho e cor, marca-texto, alinhamento, listas, tabelas, imagens (coladas, arrastadas
 * ou enviadas — ficam embutidas no texto), links, tela cheia e corretor do navegador.
 * Variáveis entram com "{" (lista do módulo em `variaveis`). `extensoes` recebe os
 * marcadores extras do módulo (menção, citação, assinatura).
 */
import { useEffect, useRef, useState } from 'react';
import { EditorContent, useEditor } from '@tiptap/react';
import StarterKit from '@tiptap/starter-kit';
import Underline from '@tiptap/extension-underline';
import TextStyle from '@tiptap/extension-text-style';
import Color from '@tiptap/extension-color';
import Highlight from '@tiptap/extension-highlight';
import TextAlign from '@tiptap/extension-text-align';
import Link from '@tiptap/extension-link';
import Table from '@tiptap/extension-table';
import TableRow from '@tiptap/extension-table-row';
import TableCell from '@tiptap/extension-table-cell';
import TableHeader from '@tiptap/extension-table-header';
import Placeholder from '@tiptap/extension-placeholder';
import { ImagemComLargura, TamanhoFonte, variavel } from './extensoes';

const TAMANHOS = ['10px', '12px', '14px', '16px', '18px', '24px', '32px'];
const MAX_IMAGEM = 1600; // px no lado maior; o resto é reduzido no navegador antes de embutir

/** Lê a imagem, reduz se for grande e devolve o data URI (JPEG; PNG pequeno fica PNG). */
function imagemParaDataUri(arquivo) {
    return new Promise((ok, erro) => {
        if (! /^image\/(png|jpe?g|gif|webp)$/.test(arquivo.type)) { erro(new Error('Formato de imagem não aceito.')); return; }
        const leitor = new FileReader();
        leitor.onerror = () => erro(new Error('Não foi possível ler a imagem.'));
        leitor.onload = () => {
            const img = new window.Image();
            img.onload = () => {
                const escala = Math.min(1, MAX_IMAGEM / Math.max(img.width, img.height));
                if (escala === 1 && arquivo.size < 400_000) { ok({ src: leitor.result, largura: img.width }); return; }
                const c = document.createElement('canvas');
                c.width = Math.round(img.width * escala); c.height = Math.round(img.height * escala);
                const ctx = c.getContext('2d');
                ctx.fillStyle = '#fff'; ctx.fillRect(0, 0, c.width, c.height);
                ctx.drawImage(img, 0, 0, c.width, c.height);
                ok({ src: c.toDataURL('image/jpeg', 0.85), largura: c.width });
            };
            img.onerror = () => erro(new Error('Imagem inválida.'));
            img.src = leitor.result;
        };
        leitor.readAsDataURL(arquivo);
    });
}

export default function EditorTexto({ html, onChange, minHeight = 350, placeholder = '', variaveis = [], extensoes = [], compacto = false, erro = null }) {
    const variaveisRef = useRef(variaveis);
    variaveisRef.current = variaveis;
    const [telaCheia, setTelaCheia] = useState(false);
    const [aviso, setAviso] = useState('');
    const arquivoRef = useRef(null);
    const editorRef = useRef(null);

    const inserirImagens = (editor, arquivos) => {
        [...arquivos].filter((a) => a.type.startsWith('image/')).forEach((a) => {
            imagemParaDataUri(a).then(({ src, largura }) => {
                editor.chain().focus().setImage({ src, width: Math.min(largura, 600) }).run();
            }).catch((e) => setAviso(e.message));
        });
    };

    const editor = useEditor({
        extensions: [
            StarterKit.configure({ heading: { levels: [1, 2, 3] } }),
            Underline, TextStyle, Color, TamanhoFonte,
            Highlight.configure({ multicolor: true }),
            TextAlign.configure({ types: ['heading', 'paragraph'] }),
            Link.configure({ openOnClick: false, autolink: true, HTMLAttributes: { rel: 'noopener noreferrer', target: '_blank' } }),
            Table.configure({ resizable: true }), TableRow, TableHeader, TableCell,
            ImagemComLargura,
            Placeholder.configure({ placeholder }),
            variavel(variaveisRef),
            ...extensoes,
        ],
        content: html || '',
        editorProps: {
            attributes: { spellcheck: 'true', lang: 'pt-BR', class: 'focus:outline-none' },
            handlePaste: (view, evento) => {
                const imagens = [...(evento.clipboardData?.files || [])].filter((a) => a.type.startsWith('image/'));
                if (! imagens.length) return false;
                inserirImagens(editorRef.current, imagens);
                return true;
            },
            handleDrop: (view, evento) => {
                const imagens = [...(evento.dataTransfer?.files || [])].filter((a) => a.type.startsWith('image/'));
                if (! imagens.length) return false;
                evento.preventDefault();
                inserirImagens(editorRef.current, imagens);
                return true;
            },
        },
        onUpdate: ({ editor: e }) => onChange(e.isEmpty ? '' : e.getHTML()),
    });
    editorRef.current = editor;

    // Conteúdo trocado de fora (modelo carregado, rascunho recuperado): só quando difere e sem foco
    useEffect(() => {
        if (! editor || editor.isFocused) return;
        if ((html || '') !== (editor.isEmpty ? '' : editor.getHTML())) editor.commands.setContent(html || '', false);
    }, [html, editor]);

    useEffect(() => {
        if (! telaCheia) return undefined;
        const esc = (e) => { if (e.key === 'Escape') setTelaCheia(false); };
        window.addEventListener('keydown', esc);
        return () => window.removeEventListener('keydown', esc);
    }, [telaCheia]);

    useEffect(() => { if (aviso) { const t = setTimeout(() => setAviso(''), 4000); return () => clearTimeout(t); } return undefined; }, [aviso]);

    if (! editor) return null;

    const link = () => {
        const atual = editor.getAttributes('link').href || '';
        const url = window.prompt('Endereço do link (https://…). Deixe vazio para remover.', atual);
        if (url === null) return;
        if (url.trim() === '') { editor.chain().focus().unsetLink().run(); return; }
        const href = /^(https?:\/\/|mailto:)/i.test(url.trim()) ? url.trim() : `https://${url.trim()}`;
        editor.chain().focus().extendMarkRange('link').setLink({ href }).run();
    };

    const naTabela = editor.isActive('table');
    const naImagem = editor.isActive('image');
    const estiloAtual = editor.isActive('heading', { level: 1 }) ? 'h1' : editor.isActive('heading', { level: 2 }) ? 'h2'
        : editor.isActive('heading', { level: 3 }) ? 'h3' : editor.isActive('blockquote') ? 'citacao' : 'p';
    const mudarEstilo = (v) => {
        const c = editor.chain().focus();
        if (v === 'p') c.setParagraph().run();
        else if (v === 'citacao') c.toggleBlockquote().run();
        else c.toggleHeading({ level: Number(v.slice(1)) }).run();
    };

    return (
        <div className={telaCheia ? 'fixed inset-0 z-[70] bg-gray-100 flex flex-col' : ''}>
            <div className={`border ${erro ? 'border-red-400' : 'border-gray-300'} rounded-md bg-white flex flex-col ${telaCheia ? 'm-4 flex-1 min-h-0 shadow-xl' : ''}`}>
                <div className="flex flex-wrap items-center gap-0.5 bg-gray-50 border-b border-gray-200 px-1.5 py-1 rounded-t-md sticky top-0 z-10">
                    <Botao titulo="Desfazer (Ctrl+Z)" icone="fa-rotate-left" onClick={() => editor.chain().focus().undo().run()} desabilitado={! editor.can().undo()} />
                    <Botao titulo="Refazer (Ctrl+Y)" icone="fa-rotate-right" onClick={() => editor.chain().focus().redo().run()} desabilitado={! editor.can().redo()} />
                    <Sep />
                    {! compacto && (
                        <select value={estiloAtual} onChange={(e) => mudarEstilo(e.target.value)} title="Estilo do parágrafo"
                            className="text-xs border border-gray-200 rounded px-1.5 py-1 bg-white">
                            <option value="p">Parágrafo</option><option value="h1">Título 1</option><option value="h2">Título 2</option>
                            <option value="h3">Título 3</option><option value="citacao">Citação</option>
                        </select>
                    )}
                    <select value={editor.getAttributes('textStyle').fontSize || ''} title="Tamanho da letra"
                        onChange={(e) => (e.target.value ? editor.chain().focus().setFontSize(e.target.value).run() : editor.chain().focus().unsetFontSize().run())}
                        className="text-xs border border-gray-200 rounded px-1 py-1 bg-white ml-0.5">
                        <option value="">Tam.</option>
                        {TAMANHOS.map((t) => <option key={t} value={t}>{parseInt(t, 10)}</option>)}
                    </select>
                    <Sep />
                    <Botao titulo="Negrito (Ctrl+B)" icone="fa-bold" ativo={editor.isActive('bold')} onClick={() => editor.chain().focus().toggleBold().run()} />
                    <Botao titulo="Itálico (Ctrl+I)" icone="fa-italic" ativo={editor.isActive('italic')} onClick={() => editor.chain().focus().toggleItalic().run()} />
                    <Botao titulo="Sublinhado (Ctrl+U)" icone="fa-underline" ativo={editor.isActive('underline')} onClick={() => editor.chain().focus().toggleUnderline().run()} />
                    <Botao titulo="Tachado" icone="fa-strikethrough" ativo={editor.isActive('strike')} onClick={() => editor.chain().focus().toggleStrike().run()} />
                    <label title="Cor da letra" className="relative w-7 h-7 flex items-center justify-center rounded hover:bg-gray-200 cursor-pointer">
                        <i className="fas fa-font text-xs" style={{ color: editor.getAttributes('textStyle').color || '#374151' }} />
                        <input type="color" className="absolute inset-0 opacity-0 cursor-pointer" value={editor.getAttributes('textStyle').color || '#000000'}
                            onChange={(e) => editor.chain().focus().setColor(e.target.value).run()} />
                    </label>
                    <Botao titulo="Marca-texto" icone="fa-highlighter" ativo={editor.isActive('highlight')} onClick={() => editor.chain().focus().toggleHighlight({ color: '#fef08a' }).run()} />
                    <Botao titulo="Limpar formatação" icone="fa-eraser" onClick={() => editor.chain().focus().unsetAllMarks().run()} />
                    <Sep />
                    {['left', 'center', 'right', 'justify'].map((a) => (
                        <Botao key={a} titulo={{ left: 'Alinhar à esquerda', center: 'Centralizar', right: 'Alinhar à direita', justify: 'Justificar' }[a]}
                            icone={`fa-align-${a}`} ativo={editor.isActive({ textAlign: a })} onClick={() => editor.chain().focus().setTextAlign(a).run()} />
                    ))}
                    <Sep />
                    <Botao titulo="Lista com marcadores" icone="fa-list-ul" ativo={editor.isActive('bulletList')} onClick={() => editor.chain().focus().toggleBulletList().run()} />
                    <Botao titulo="Lista numerada" icone="fa-list-ol" ativo={editor.isActive('orderedList')} onClick={() => editor.chain().focus().toggleOrderedList().run()} />
                    <Sep />
                    <Botao titulo="Link" icone="fa-link" ativo={editor.isActive('link')} onClick={link} />
                    <Botao titulo="Imagem" icone="fa-image" onClick={() => arquivoRef.current?.click()} />
                    <input ref={arquivoRef} type="file" accept="image/png,image/jpeg,image/gif,image/webp" multiple className="hidden"
                        onChange={(e) => { inserirImagens(editor, e.target.files); e.target.value = ''; }} />
                    <Botao titulo="Inserir tabela" icone="fa-table" onClick={() => editor.chain().focus().insertTable({ rows: 3, cols: 3, withHeaderRow: true }).run()} />
                    {! compacto && <Botao titulo="Linha horizontal" icone="fa-minus" onClick={() => editor.chain().focus().setHorizontalRule().run()} />}
                    {variaveis.length > 0 && (
                        <select value="" title="Inserir variável (ou digite { no texto)"
                            onChange={(e) => { const v = variaveis.find((x) => x.id === e.target.value); if (v) editor.chain().focus().insertContent({ type: 'variavel', attrs: { id: v.id, label: v.label } }).run(); }}
                            className="text-xs border border-gray-200 rounded px-1.5 py-1 bg-white ml-0.5">
                            <option value="">{'{ } Variável'}</option>
                            {variaveis.map((v) => <option key={v.id} value={v.id}>{v.label}</option>)}
                        </select>
                    )}
                    <span className="ml-auto" />
                    <Botao titulo={telaCheia ? 'Sair da tela cheia (Esc)' : 'Tela cheia'} icone={telaCheia ? 'fa-compress' : 'fa-expand'} onClick={() => setTelaCheia(! telaCheia)} />
                </div>

                {naTabela && (
                    <div className="flex flex-wrap items-center gap-1 border-b border-gray-200 bg-cyan-50/60 px-2 py-1 text-[11px]">
                        <span className="text-cyan-800 font-medium mr-1"><i className="fas fa-table mr-1" />Tabela:</span>
                        <MiniBotao onClick={() => editor.chain().focus().addRowAfter().run()}>+ linha</MiniBotao>
                        <MiniBotao onClick={() => editor.chain().focus().addColumnAfter().run()}>+ coluna</MiniBotao>
                        <MiniBotao onClick={() => editor.chain().focus().deleteRow().run()}>− linha</MiniBotao>
                        <MiniBotao onClick={() => editor.chain().focus().deleteColumn().run()}>− coluna</MiniBotao>
                        <MiniBotao onClick={() => editor.chain().focus().mergeOrSplit().run()}>Juntar/separar células</MiniBotao>
                        <MiniBotao onClick={() => editor.chain().focus().toggleHeaderRow().run()}>Cabeçalho</MiniBotao>
                        <MiniBotao perigo onClick={() => editor.chain().focus().deleteTable().run()}>Excluir tabela</MiniBotao>
                    </div>
                )}
                {naImagem && (
                    <div className="flex flex-wrap items-center gap-1 border-b border-gray-200 bg-cyan-50/60 px-2 py-1 text-[11px]">
                        <span className="text-cyan-800 font-medium mr-1"><i className="fas fa-image mr-1" />Imagem:</span>
                        {[['Pequena', 200], ['Média', 400], ['Grande', 600]].map(([r, w]) => (
                            <MiniBotao key={w} onClick={() => editor.chain().focus().updateAttributes('image', { width: w }).run()}>{r}</MiniBotao>
                        ))}
                        <MiniBotao perigo onClick={() => editor.chain().focus().deleteSelection().run()}>Remover</MiniBotao>
                    </div>
                )}

                <div className={`texto-rico px-4 py-3 cursor-text ${telaCheia ? 'flex-1 overflow-y-auto' : ''}`} style={telaCheia ? {} : { minHeight }}
                    onClick={() => editor.chain().focus().run()}>
                    <div className={telaCheia ? 'max-w-4xl mx-auto bg-white' : ''}>
                        <EditorContent editor={editor} />
                    </div>
                </div>
            </div>
            {aviso && <p className="mt-1 text-xs text-amber-700"><i className="fas fa-triangle-exclamation mr-1" />{aviso}</p>}
            {! telaCheia && variaveis.length > 0 && (
                <p className="mt-1 text-[11px] text-gray-500">Digite <kbd className="px-1 rounded bg-gray-100 border border-gray-200">{'{'}</kbd> para inserir uma variável — ela é trocada pelo valor ao gerar o documento.</p>
            )}
        </div>
    );
}

function Botao({ titulo, icone, onClick, ativo = false, desabilitado = false }) {
    return (
        <button type="button" title={titulo} aria-label={titulo} onMouseDown={(e) => e.preventDefault()} onClick={onClick} disabled={desabilitado}
            className={`w-7 h-7 flex items-center justify-center rounded text-xs ${ativo ? 'bg-cyan-100 text-cyan-800' : 'text-gray-600 hover:bg-gray-200'} disabled:opacity-30`}>
            <i className={`fas ${icone}`} />
        </button>
    );
}

function MiniBotao({ onClick, children, perigo = false }) {
    return (
        <button type="button" onMouseDown={(e) => e.preventDefault()} onClick={onClick}
            className={`px-2 py-0.5 rounded border ${perigo ? 'border-red-200 text-red-700 hover:bg-red-50' : 'border-gray-200 text-gray-700 hover:bg-white'}`}>{children}</button>
    );
}

const Sep = () => <span className="w-px h-5 bg-gray-200 mx-1" />;

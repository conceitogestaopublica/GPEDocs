/**
 * Editor WYSIWYG estilo Word baseado em contentEditable.
 * - Toolbar com formatacao basica (negrito, italico, alinhamento, listas, estilo)
 * - Suporte a Tab para indentacao
 * - Aceita HTML inicial e emite onChange a cada modificacao
 */
import { useEffect, useRef } from 'react';

export default function RichEditor({ html, onChange, minHeight = 350, placeholder = '' }) {
    const ref = useRef(null);

    // Sincroniza apenas quando o html externo muda (evita perder cursor durante digitacao)
    useEffect(() => {
        if (ref.current && ref.current.innerHTML !== (html || '')) {
            ref.current.innerHTML = html || '';
        }
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [html]);

    const exec = (cmd, arg = null) => {
        document.execCommand(cmd, false, arg);
        ref.current?.focus();
        if (ref.current) onChange(ref.current.innerHTML);
    };

    const handleKeyDown = (e) => {
        // Tab → insere indentacao (4 espacos) em vez de pular foco
        if (e.key === 'Tab') {
            e.preventDefault();
            if (e.shiftKey) {
                document.execCommand('outdent', false);
            } else {
                document.execCommand('indent', false);
            }
            if (ref.current) onChange(ref.current.innerHTML);
        }
    };

    return (
        <div className="border border-gray-300 rounded-md overflow-hidden bg-white">
            {/* Toolbar */}
            <div className="flex items-center gap-0.5 bg-gray-50 border-b border-gray-200 px-2 py-1 flex-wrap">
                {/* Estilo de paragrafo */}
                <select className="text-[10px] border border-gray-200 rounded px-1.5 py-1 bg-white mr-1"
                    onChange={(e) => { exec('formatBlock', e.target.value); e.target.value = ''; }}
                    defaultValue="">
                    <option value="" disabled>Estilo</option>
                    <option value="P">Paragrafo normal</option>
                    <option value="H1">Titulo 1</option>
                    <option value="H2">Titulo 2</option>
                    <option value="H3">Titulo 3</option>
                    <option value="BLOCKQUOTE">Citacao</option>
                    <option value="PRE">Pre-formatado</option>
                </select>

                {/* Fonte */}
                <select className="text-[10px] border border-gray-200 rounded px-1.5 py-1 bg-white mr-1"
                    onChange={(e) => { exec('fontName', e.target.value); e.target.value = ''; }}
                    defaultValue="">
                    <option value="" disabled>Fonte</option>
                    <option value="Arial">Arial</option>
                    <option value="'Times New Roman'">Times New Roman</option>
                    <option value="'Courier New'">Courier New</option>
                    <option value="Verdana">Verdana</option>
                    <option value="Calibri">Calibri</option>
                    <option value="Georgia">Georgia</option>
                </select>

                {/* Tamanho */}
                <select className="text-[10px] border border-gray-200 rounded px-1.5 py-1 bg-white"
                    onChange={(e) => { exec('fontSize', e.target.value); e.target.value = ''; }}
                    defaultValue="">
                    <option value="" disabled>Tam</option>
                    <option value="1">8</option>
                    <option value="2">10</option>
                    <option value="3">12</option>
                    <option value="4">14</option>
                    <option value="5">18</option>
                    <option value="6">24</option>
                    <option value="7">36</option>
                </select>

                <Sep />

                <BtnTb onClick={() => exec('bold')} title="Negrito (Ctrl+B)"><b>N</b></BtnTb>
                <BtnTb onClick={() => exec('italic')} title="Italico (Ctrl+I)"><i>I</i></BtnTb>
                <BtnTb onClick={() => exec('underline')} title="Sublinhado (Ctrl+U)"><u>S</u></BtnTb>
                <BtnTb onClick={() => exec('strikeThrough')} title="Tachado"><s>T</s></BtnTb>

                <Sep />

                <BtnTb onClick={() => exec('justifyLeft')} title="Alinhar a esquerda"><i className="fas fa-align-left" /></BtnTb>
                <BtnTb onClick={() => exec('justifyCenter')} title="Centralizar"><i className="fas fa-align-center" /></BtnTb>
                <BtnTb onClick={() => exec('justifyRight')} title="Alinhar a direita"><i className="fas fa-align-right" /></BtnTb>
                <BtnTb onClick={() => exec('justifyFull')} title="Justificar"><i className="fas fa-align-justify" /></BtnTb>

                <Sep />

                <BtnTb onClick={() => exec('insertUnorderedList')} title="Lista com marcadores"><i className="fas fa-list-ul" /></BtnTb>
                <BtnTb onClick={() => exec('insertOrderedList')} title="Lista numerada"><i className="fas fa-list-ol" /></BtnTb>
                <BtnTb onClick={() => exec('outdent')} title="Diminuir recuo"><i className="fas fa-outdent" /></BtnTb>
                <BtnTb onClick={() => exec('indent')} title="Aumentar recuo (Tab)"><i className="fas fa-indent" /></BtnTb>

                <Sep />

                <BtnTb onClick={() => exec('insertHorizontalRule')} title="Linha horizontal"><i className="fas fa-minus" /></BtnTb>
                <BtnTb onClick={() => {
                    const url = prompt('URL do link:');
                    if (url) exec('createLink', url);
                }} title="Inserir link"><i className="fas fa-link" /></BtnTb>

                <Sep />

                <input type="color" title="Cor do texto" className="w-6 h-6 border-0 cursor-pointer p-0"
                    onChange={(e) => exec('foreColor', e.target.value)} />

                <Sep />

                <BtnTb onClick={() => exec('undo')} title="Desfazer (Ctrl+Z)"><i className="fas fa-undo" /></BtnTb>
                <BtnTb onClick={() => exec('redo')} title="Refazer (Ctrl+Y)"><i className="fas fa-redo" /></BtnTb>
                <BtnTb onClick={() => exec('removeFormat')} title="Limpar formatacao"><i className="fas fa-eraser" /></BtnTb>
            </div>

            {/* Area de edicao — estilo "pagina Word" */}
            <div
                ref={ref}
                contentEditable
                suppressContentEditableWarning
                onInput={(e) => onChange(e.currentTarget.innerHTML)}
                onKeyDown={handleKeyDown}
                onPaste={(e) => {
                    // Preserva HTML colado quando vier de Word/Excel
                    const html = e.clipboardData.getData('text/html');
                    const text = e.clipboardData.getData('text/plain');
                    if (html) {
                        e.preventDefault();
                        document.execCommand('insertHTML', false, html);
                        if (ref.current) onChange(ref.current.innerHTML);
                    } else if (text) {
                        // Mantem comportamento padrao para texto puro
                    }
                }}
                data-placeholder={placeholder}
                className="oficio-editor p-6 text-sm focus:outline-none overflow-y-auto"
                style={{
                    minHeight: `${minHeight}px`,
                    maxHeight: '70vh',
                    wordBreak: 'break-word',
                    lineHeight: 1.6,
                    fontFamily: "'Times New Roman', serif",
                    fontSize: '12pt',
                }}
            />

            <style>{`
                .oficio-editor:empty::before {
                    content: attr(data-placeholder);
                    color: #9ca3af;
                }
                .oficio-editor p { margin: 0 0 0.5em 0; }
                .oficio-editor h1 { font-size: 1.8em; font-weight: bold; margin: 0.6em 0; }
                .oficio-editor h2 { font-size: 1.4em; font-weight: bold; margin: 0.5em 0; }
                .oficio-editor h3 { font-size: 1.2em; font-weight: bold; margin: 0.4em 0; }
                .oficio-editor ul, .oficio-editor ol { margin-left: 2em; margin-bottom: 0.5em; }
                .oficio-editor blockquote { border-left: 3px solid #ddd; margin: 0.5em 0; padding-left: 1em; color: #555; }
                .oficio-editor table { border-collapse: collapse; }
                .oficio-editor table td, .oficio-editor table th { border: 1px solid #ddd; padding: 4px 8px; }
            `}</style>
        </div>
    );
}

function BtnTb({ onClick, title, children }) {
    return (
        <button type="button" onClick={onClick} title={title}
            className="w-7 h-7 text-xs text-gray-700 hover:bg-gray-200 rounded transition-colors flex items-center justify-center">
            {children}
        </button>
    );
}

function Sep() {
    return <div className="w-px h-5 bg-gray-300 mx-0.5" />;
}

/**
 * Extensões do editor de texto (TR 4.16): tamanho de fonte, imagem com largura e os
 * marcadores digitados por caractere especial — variável ({), menção (@), citação de
 * documento (#) e pedido de assinatura (!). Cada marcador vira um <span>/<a> com
 * data-type/data-id/data-label, que o servidor lê depois de salvar.
 */
import { Extension } from '@tiptap/core';
import Image from '@tiptap/extension-image';
import Mention from '@tiptap/extension-mention';
import { ReactRenderer } from '@tiptap/react';
import { PluginKey } from '@tiptap/pm/state';
import ListaSugestoes from './ListaSugestoes';

export const TamanhoFonte = Extension.create({
    name: 'tamanhoFonte',
    addGlobalAttributes() {
        return [{
            types: ['textStyle'],
            attributes: {
                fontSize: {
                    default: null,
                    parseHTML: (el) => el.style.fontSize || null,
                    renderHTML: (a) => (a.fontSize ? { style: `font-size: ${a.fontSize}` } : {}),
                },
            },
        }];
    },
    addCommands() {
        return {
            setFontSize: (tamanho) => ({ chain }) => chain().setMark('textStyle', { fontSize: tamanho }).run(),
            unsetFontSize: () => ({ chain }) => chain().setMark('textStyle', { fontSize: null }).removeEmptyTextStyle().run(),
        };
    },
});

/** Imagem embutida (data URI) com largura em px escolhida na barra. */
export const ImagemComLargura = Image.extend({
    addAttributes() {
        return {
            ...this.parent?.(),
            width: {
                default: null,
                parseHTML: (el) => el.getAttribute('width'),
                renderHTML: (a) => (a.width ? { width: a.width } : {}),
            },
        };
    },
}).configure({ inline: false, allowBase64: true });

/** Caixa de sugestões que segue o cursor (sem dependência de popup externo). */
function renderizar() {
    let comp;
    const posicionar = (props) => {
        const r = props.clientRect?.();
        if (!r || !comp) return;
        const el = comp.element;
        el.style.left = `${Math.min(r.left, window.innerWidth - 320)}px`;
        el.style.top = `${r.bottom + 4}px`;
    };
    return {
        onStart: (props) => {
            comp = new ReactRenderer(ListaSugestoes, { props, editor: props.editor });
            Object.assign(comp.element.style, { position: 'fixed', zIndex: 9999 });
            document.body.appendChild(comp.element);
            posicionar(props);
        },
        onUpdate: (props) => { comp?.updateProps(props); posicionar(props); },
        onKeyDown: (props) => {
            if (props.event.key === 'Escape') { comp?.element.remove(); return true; }
            return comp?.ref?.onKeyDown(props) ?? false;
        },
        onExit: () => { comp?.element.remove(); comp?.destroy(); comp = null; },
    };
}

/**
 * Marcador digitado por caractere especial. `buscar(termo)` devolve (ou promete)
 * [{ id, label, detalhe? }]; `atributos(no)` monta o HTML salvo.
 */
export function marcador({ nome, char, buscar, tag = 'span', texto, atributos = () => ({}) }) {
    return Mention.extend({
        name: nome,
        parseHTML() { return [{ tag: `${tag}[data-type="${nome}"]` }]; },
        renderHTML({ node }) {
            return [tag, { 'data-type': nome, 'data-id': node.attrs.id, 'data-label': node.attrs.label, ...atributos(node.attrs) }, texto(node.attrs)];
        },
        renderText({ node }) { return texto(node.attrs); },
    }).configure({
        suggestion: {
            char,
            pluginKey: new PluginKey(`sugestao-${nome}`),
            allowSpaces: char !== '{',
            items: ({ query }) => buscar(query),
            render: renderizar,
        },
    });
}

/** Variável de conteúdo ({{numero}}, {{data}}…) — o servidor troca pelo valor ao gerar o documento. */
export const variavel = (listaRef) => marcador({
    nome: 'variavel',
    char: '{',
    texto: (a) => `{{${a.id}}}`,
    buscar: (q) => {
        const t = (q || '').toLowerCase();
        return (listaRef.current || []).filter((v) => v.id.includes(t) || v.label.toLowerCase().includes(t))
            .slice(0, 12);
    },
});

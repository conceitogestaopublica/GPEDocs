/*
 * Gera os dois .docx do Termo de Referência a partir dos mesmos arquivos .md.
 *
 * A versão enxuta é SUBCONJUNTO da completa, selecionada por faixa de
 * requisito — nunca um texto paralelo. É o que impede as duas de divergirem
 * quando um requisito for corrigido.
 */
const fs = require('node:fs')
const path = require('node:path')
const {
  AlignmentType,
  BorderStyle,
  Document,
  Footer,
  HeadingLevel,
  LevelFormat,
  PageNumber,
  Packer,
  Paragraph,
  ShadingType,
  Table,
  TableCell,
  TableRow,
  TextRun,
  WidthType,
  TableOfContents,
} = require('docx')

const DIR = process.argv[2]
const OUT = process.argv[3]

/* Largura útil em DXA: A4 (11906) menos as margens de 1134 cada. */
const LARGURA = 11906 - 1134 * 2

/* ──────────────────────── seleção da versão enxuta ──────────────────────── */

/* Faixas mantidas na versão enxuta. Técnicos e de segurança entram inteiros:
 * são curtos e nenhum município deveria dispensá-los. Ficam de fora a carta de
 * serviços e as solicitações do cidadão (RF-300 a RF-339) e a integração com
 * sistemas (RF-400 a RF-449). */
const ENXUTA_FAIXAS = [
  ['RT', 1, 999],
  ['RS', 1, 999],
  ['RF', 1, 299], // gestão documental, assinatura, processo e comunicações
  ['RF', 340, 399], // autenticidade
  ['RF', 450, 499], // estrutura organizacional
]

/* Seções (## e ###) que a enxuta não traz. */
const ENXUTA_SECOES_FORA = ['6.15.', '6.16.', '6.18.']

function naEnxuta(id) {
  const m = /^(RT|RS|RF)-(\d{3})$/.exec(id)
  if (!m) return true
  const n = Number(m[2])
  return ENXUTA_FAIXAS.some(([p, a, b]) => p === m[1] && n >= a && n <= b)
}

/* ──────────────────────── leitura e marcação do .md ─────────────────────── */

function lerFontes() {
  return fs
    .readdirSync(DIR)
    /* So os arquivos NUMERADOS compoem o TR. O README fala SOBRE o TR e nao
     * pode entrar dentro dele — entrou, na primeira geracao. */
    .filter(f => /^\d\d-.*\.md$/.test(f))
    .sort()
    .map(f => fs.readFileSync(path.join(DIR, f), 'utf8').replace(/\r\n/g, '\n'))
    .join('\n\n')
}

/* Blocos: heading, para, table, quote, hr. */
function blocos(texto) {
  const out = []
  const linhas = texto.split('\n')
  let i = 0
  while (i < linhas.length) {
    const l = linhas[i]
    if (!l.trim()) { i++; continue }

    if (/^---+$/.test(l.trim())) { out.push({ t: 'hr' }); i++; continue }

    const h = /^(#{1,4})\s+(.*)$/.exec(l)
    if (h) { out.push({ t: 'h', nivel: h[1].length, texto: h[2].trim() }); i++; continue }

    if (l.trimStart().startsWith('|')) {
      const linhasTab = []
      while (i < linhas.length && linhas[i].trimStart().startsWith('|')) {
        linhasTab.push(linhas[i].trim()); i++
      }
      const celulas = linhasTab
        .filter(x => !/^\|[\s:|-]+\|$/.test(x))
        .map(x => x.replace(/^\|/, '').replace(/\|$/, '').split('|').map(c => c.trim()))
      if (celulas.length) out.push({ t: 'tab', linhas: celulas })
      continue
    }

    if (l.trimStart().startsWith('>')) {
      const partes = []
      while (i < linhas.length && linhas[i].trimStart().startsWith('>')) {
        partes.push(linhas[i].replace(/^\s*>\s?/, '')); i++
      }
      out.push({ t: 'quote', texto: partes.join(' ').trim() })
      continue
    }

    /* Parágrafo: junta linhas até a próxima em branco ou marcador de bloco. */
    const partes = []
    while (
      i < linhas.length && linhas[i].trim() &&
      !/^(#{1,4})\s/.test(linhas[i]) &&
      !linhas[i].trimStart().startsWith('|') &&
      !linhas[i].trimStart().startsWith('>') &&
      !/^---+$/.test(linhas[i].trim())
    ) { partes.push(linhas[i].trim()); i++ }
    out.push({ t: 'p', texto: partes.join(' ') })
  }
  return out
}

/* ──────────────────────────── montagem do docx ─────────────────────────── */

/* **negrito** e *itálico* viram runs; o resto é texto. */
function runs(texto, base = {}) {
  const saida = []
  const re = /(\*\*[^*]+\*\*|\*[^*]+\*|`[^`]+`)/g
  let ultimo = 0
  let m
  while ((m = re.exec(texto))) {
    if (m.index > ultimo) saida.push(new TextRun({ ...base, text: texto.slice(ultimo, m.index) }))
    const tok = m[0]
    if (tok.startsWith('**')) saida.push(new TextRun({ ...base, text: tok.slice(2, -2), bold: true }))
    else if (tok.startsWith('`')) saida.push(new TextRun({ ...base, text: tok.slice(1, -1), font: 'Consolas' }))
    else saida.push(new TextRun({ ...base, text: tok.slice(1, -1), italics: true }))
    ultimo = m.index + tok.length
  }
  if (ultimo < texto.length) saida.push(new TextRun({ ...base, text: texto.slice(ultimo) }))
  return saida.length ? saida : [new TextRun({ ...base, text: '' })]
}

const NIVEL = {
  1: HeadingLevel.TITLE,
  2: HeadingLevel.HEADING_1,
  3: HeadingLevel.HEADING_2,
  4: HeadingLevel.HEADING_3,
}

function celula(texto, cabecalho, largura) {
  return new TableCell({
    width: { size: largura, type: WidthType.DXA },
    shading: cabecalho
      ? { type: ShadingType.CLEAR, fill: 'E8EDF2', color: 'auto' }
      : undefined,
    margins: { top: 60, bottom: 60, left: 110, right: 110 },
    children: [
      new Paragraph({
        spacing: { before: 0, after: 0 },
        children: runs(texto, cabecalho ? { bold: true, size: 19 } : { size: 19 }),
      }),
    ],
  })
}

function tabela(linhas) {
  const colunas = Math.max(...linhas.map(l => l.length))
  const base = Math.floor(LARGURA / colunas)
  /* A soma das colunas tem de fechar a largura da tabela. */
  const larguras = Array.from({ length: colunas }, (_, k) =>
    k === colunas - 1 ? LARGURA - base * (colunas - 1) : base)
  return new Table({
    width: { size: LARGURA, type: WidthType.DXA },
    columnWidths: larguras,
    rows: linhas.map((l, idx) => new TableRow({
      tableHeader: idx === 0,
      children: larguras.map((w, k) => celula(l[k] ?? '', idx === 0, w)),
    })),
  })
}

function montar(bs, enxuta) {
  const filhos = []
  let pularSecao = false

  for (const b of bs) {
    if (b.t === 'h') {
      /* Numeração da seção, p/ cortar blocos inteiros na versão enxuta. */
      const num = (/^(\d+\.\d+\.)/.exec(b.texto) || [])[1]
      if (enxuta && b.nivel <= 3) {
        pularSecao = Boolean(num && ENXUTA_SECOES_FORA.includes(num))
      }
      if (pularSecao) continue
      filhos.push(new Paragraph({
        heading: NIVEL[b.nivel],
        spacing: { before: b.nivel <= 2 ? 360 : 240, after: 120 },
        keepNext: true,
        children: runs(b.texto),
      }))
      continue
    }
    if (pularSecao) continue

    if (b.t === 'hr') {
      filhos.push(new Paragraph({
        spacing: { before: 120, after: 120 },
        border: { bottom: { style: BorderStyle.SINGLE, size: 6, color: 'C8D2DC' } },
        children: [new TextRun('')],
      }))
      continue
    }

    if (b.t === 'tab') { filhos.push(tabela(b.linhas)); filhos.push(new Paragraph({ spacing: { after: 160 }, children: [new TextRun('')] })); continue }

    if (b.t === 'quote') {
      filhos.push(new Paragraph({
        spacing: { before: 120, after: 160 },
        indent: { left: 340 },
        border: { left: { style: BorderStyle.SINGLE, size: 12, color: '7A93AB', space: 10 } },
        children: runs(b.texto, { italics: true, size: 20 }),
      }))
      continue
    }

    /* Requisito: a enxuta descarta o que está fora das faixas. */
    const req = /^\*\*((?:RT|RS|RF)-\d{3})\.\*\*/.exec(b.texto)
    if (enxuta && req && !naEnxuta(req[1])) continue

    filhos.push(new Paragraph({
      spacing: { after: 120, line: 276 },
      alignment: AlignmentType.JUSTIFIED,
      children: runs(b.texto),
    }))
  }
  return filhos
}

function documento(filhos, subtitulo) {
  return new Document({
    styles: {
      default: {
        document: { run: { font: 'Calibri', size: 22 } },
        title: { run: { font: 'Calibri', size: 40, bold: true, color: '1F3864' }, paragraph: { spacing: { after: 240 }, alignment: AlignmentType.CENTER } },
        heading1: { run: { font: 'Calibri', size: 28, bold: true, color: '1F3864' } },
        heading2: { run: { font: 'Calibri', size: 24, bold: true, color: '2E5496' } },
        heading3: { run: { font: 'Calibri', size: 22, bold: true, color: '2E5496' } },
      },
    },
    numbering: { config: [] },
    sections: [{
      properties: { page: { margin: { top: 1134, bottom: 1134, left: 1134, right: 1134 } } },
      footers: {
        default: new Footer({
          children: [new Paragraph({
            alignment: AlignmentType.CENTER,
            children: [
              new TextRun({ text: subtitulo + '  ·  ', size: 16, color: '666666' }),
              new TextRun({ children: ['Página ', PageNumber.CURRENT, ' de ', PageNumber.TOTAL_PAGES], size: 16, color: '666666' }),
            ],
          })],
        }),
      },
      children: [
        new Paragraph({ spacing: { after: 200 }, alignment: AlignmentType.CENTER, children: [new TextRun({ text: 'SUMÁRIO', bold: true, size: 26, color: '1F3864' })] }),
        new TableOfContents('Sumário', { hyperlink: true, headingStyleRange: '1-3' }),
        new Paragraph({ pageBreakBefore: true, children: [new TextRun('')] }),
        ...filhos,
      ],
    }],
  })
}

/* ─────────────────────────────── execução ──────────────────────────────── */

const bs = blocos(lerFontes())

async function gravar(enxuta, arquivo, subtitulo) {
  const filhos = montar(bs, enxuta)
  const buf = await Packer.toBuffer(documento(filhos, subtitulo))
  fs.writeFileSync(path.join(OUT, arquivo), buf)
  const reqs = filhos.filter(p => p.constructor.name === 'Paragraph').length
  console.log(arquivo, '→', (buf.length / 1024).toFixed(0) + ' KB,', filhos.length, 'blocos')
}

/* Planilha com todos os requisitos: é o Anexo I e o acervo para responder edital. */
function gravarCsv() {
  const linhas = ['id;modulo;subsecao;requisito;na_versao_enxuta']
  let modulo = ''
  let subsecao = ''
  for (const b of bs) {
    if (b.t === 'h') {
      if (b.nivel <= 3) { modulo = b.texto; subsecao = '' }
      else subsecao = b.texto
      continue
    }
    if (b.t !== 'p') continue
    const m = /^\*\*((?:RT|RS|RF)-\d{3})\.\*\*\s*(.*)$/.exec(b.texto)
    if (!m) continue
    const texto = m[2].replace(/\*\*|_/g, '').replace(/;/g, ',')
    const fora = ENXUTA_SECOES_FORA.some(n => modulo.startsWith(n)) || !naEnxuta(m[1])
    linhas.push([m[1], modulo, subsecao, texto, fora ? 'nao' : 'sim'].join(';'))
  }
  fs.writeFileSync(path.join(OUT, 'requisitos-gpedocs.csv'), '﻿' + linhas.join('\r\n') + '\r\n')
  console.log('requisitos-gpedocs.csv →', linhas.length - 1, 'requisitos')
}

;(async () => {
  gravarCsv()
  await gravar(false, 'TR-completo.docx', 'Termo de Referência — versão completa')
  await gravar(true, 'TR-enxuto.docx', 'Termo de Referência — versão sem portal do cidadão e sem integração')
})()

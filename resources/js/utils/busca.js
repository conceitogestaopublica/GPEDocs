/** Minúsculas e sem acento: base do filtro e do realce da busca (insensível a caixa/acento). */
export function norm(s) {
    return String(s ?? '').toLowerCase().normalize('NFD').replace(/\p{M}/gu, '');
}

/** Caixa de título para exibir o grupo (seções vêm em CAIXA ALTA). */
export function cap(s) {
    return String(s ?? '').toLowerCase().replace(/(^|\s|\/|\()\p{L}/gu, (c) => c.toUpperCase());
}

/** Termos da busca normalizados (vazios descartados). */
export function termosDe(query) {
    return norm(query).split(/\s+/).filter(Boolean);
}

/**
 * Divide `texto` em trechos marcando onde aparecem os termos da busca — comparando sem
 * acento ("acao" realça "Ação") mas devolvendo o texto ORIGINAL. Termos com menos de 2
 * letras não realçam (poluiria a linha inteira).
 *
 * @returns {{ texto: string, realce: boolean }[]}
 */
export function trechosRealcados(texto, query) {
    const original = String(texto ?? '');
    const termos = termosDe(query).filter((t) => t.length >= 2);
    if (!original || termos.length === 0) return [{ texto: original, realce: false }];

    // Texto normalizado + de qual índice original veio cada caractere dele.
    let normalizado = '';
    const origem = [];
    for (let i = 0; i < original.length; i++) {
        const n = norm(original[i]);
        for (const c of n) { normalizado += c; origem.push(i); }
    }

    const marcado = new Array(original.length).fill(false);
    for (const t of termos) {
        for (let at = normalizado.indexOf(t); at !== -1; at = normalizado.indexOf(t, at + t.length)) {
            for (let k = at; k < at + t.length; k++) marcado[origem[k]] = true;
        }
    }

    const trechos = [];
    for (let i = 0; i < original.length; i++) {
        const ultimo = trechos[trechos.length - 1];
        if (ultimo && ultimo.realce === marcado[i]) ultimo.texto += original[i];
        else trechos.push({ texto: original[i], realce: marcado[i] });
    }
    return trechos;
}

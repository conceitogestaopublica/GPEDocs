/**
 * Armazenamento no aparelho (IndexedDB) do app da fiscalização: as vistorias (com as fotos em Blob)
 * e o cache dos dados baixados do GED (formulários, processos, resultados). Nada se perde sem internet.
 */
const NOME = 'gpe-fiscal';
let conexao = null;

function abrir() {
    if (conexao) return conexao;
    conexao = new Promise((ok, falha) => {
        const r = indexedDB.open(NOME, 1);
        r.onupgradeneeded = () => {
            const db = r.result;
            if (! db.objectStoreNames.contains('vistorias')) db.createObjectStore('vistorias', { keyPath: 'uuid' });
            if (! db.objectStoreNames.contains('cache')) db.createObjectStore('cache');
        };
        r.onsuccess = () => ok(r.result);
        r.onerror = () => falha(r.error);
    });
    return conexao;
}

async function operar(loja, modo, fn) {
    const db = await abrir();
    return new Promise((ok, falha) => {
        const tx = db.transaction(loja, modo);
        const req = fn(tx.objectStore(loja));
        tx.oncomplete = () => ok(req?.result);
        tx.onerror = () => falha(tx.error);
    });
}

export const vistorias = {
    todas: () => operar('vistorias', 'readonly', (s) => s.getAll()),
    uma: (uuid) => operar('vistorias', 'readonly', (s) => s.get(uuid)),
    salvar: (v) => operar('vistorias', 'readwrite', (s) => s.put({ ...v, atualizado_em: new Date().toISOString() })),
    excluir: (uuid) => operar('vistorias', 'readwrite', (s) => s.delete(uuid)),
};

export const cache = {
    ler: (chave) => operar('cache', 'readonly', (s) => s.get(chave)),
    gravar: (chave, valor) => operar('cache', 'readwrite', (s) => s.put(valor, chave)),
};

/** Pede ao navegador para não apagar os dados do app quando o aparelho estiver com pouco espaço. */
export function pedirPersistencia() {
    try { navigator.storage?.persist?.(); } catch { /* sem suporte */ }
}

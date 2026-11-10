/**
 * GPE Assinador: programa nosso, instalado no computador do usuário, que assina com o certificado do
 * Windows (A1 instalado ou A3 no token) sem extensão de terceiros. Escuta só em 127.0.0.1 e só
 * atende os sites do GPE Docs; cada assinatura é confirmada pelo usuário na janela do programa.
 *
 * Expõe a mesma forma usada com o Web PKI (listCertificates, readCertificate, signHash), devolvendo
 * Promises — o `aguardar` de ./webPki aceita as duas.
 */
const BASE = 'http://127.0.0.1:47231';

async function chamar(caminho, opcoes = {}, limiteMs = 0) {
    const controle = limiteMs ? new AbortController() : null;
    const relogio = controle ? setTimeout(() => controle.abort(), limiteMs) : null;
    try {
        const resp = await fetch(BASE + caminho, {
            ...opcoes,
            headers: { 'Content-Type': 'application/json', ...(opcoes.headers || {}) },
            signal: controle?.signal,
        });
        const dados = await resp.json().catch(() => ({}));
        if (! resp.ok) throw new Error(dados?.erro || `GPE Assinador respondeu ${resp.status}.`);

        return dados;
    } finally {
        if (relogio) clearTimeout(relogio);
    }
}

/** O assinador aberto neste computador, ou null (não instalado, fechado ou bloqueado pelo navegador). */
export async function conectarAssinador() {
    try {
        const status = await chamar('/status', {}, 2000);
        if (status?.produto !== 'GPE Assinador') return null;
    } catch {
        return null;
    }

    return {
        versao: null,
        listCertificates: async () => (await chamar('/certificados')).map((c) => ({
            thumbprint: c.thumbprint,
            subjectName: c.subject_name,
            issuerName: c.issuer_name,
            validityEnd: c.validity_end,
            pkiBrazil: { cpf: c.cpf },
        })),
        readCertificate: async ({ thumbprint }) => (await chamar(`/certificados/${encodeURIComponent(thumbprint)}`)).pem,
        readChain: async ({ thumbprint }) => (await chamar(`/certificados/${encodeURIComponent(thumbprint)}`)).cadeia || [],
        signHash: async ({ thumbprint, hash, digestAlgorithm, descricao }) => (await chamar('/assinar', {
            method: 'POST',
            body: JSON.stringify({ thumbprint, hash, algoritmo: digestAlgorithm, descricao }),
        })).assinatura,
    };
}

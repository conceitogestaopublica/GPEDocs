/**
 * As operações da extensão Web PKI (listCertificates, readCertificate,
 * signHash…) não devolvem Promise do JavaScript: devolvem um objeto com
 * .success() e .fail(). `await` direto nele não espera nada — converte aqui.
 */
export function aguardar(operacao) {
    if (operacao && typeof operacao.then === 'function') return operacao;

    return new Promise((resolver, rejeitar) => {
        operacao
            .success(resolver)
            .fail((ex) => rejeitar(new Error(ex?.message || ex?.error || 'Falha na extensão Web PKI.')));
    });
}

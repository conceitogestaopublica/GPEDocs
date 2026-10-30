import useFillViewportHeight from '../Hooks/useFillViewportHeight';

/**
 * Container de listagem que rola SÓ por dentro (padrão gpe2), com o cabeçalho da tabela
 * fixo no topo (regra .tabela-rolavel no app.css) e uma faixa fixa no pé — para a linha
 * cortada pela rolagem não encostar na borda do cartão:
 *   - `paginado`: a paginação logo abaixo já é o pé; aqui fica só uma faixa fina;
 *   - senão, com `total`: a barra "N registros".
 *
 *   <TabelaRolavel total={itens.length}><table>…</table></TabelaRolavel>
 */
export default function TabelaRolavel({ total = null, paginado = false, alturaMinima, className = '', children }) {
    // alturaMinima: listas que não podem encolher demais em tela baixa (ex.: processos).
    const ref = useFillViewportHeight(alturaMinima ? { minima: alturaMinima } : undefined);

    return (
        <div ref={ref} className={`tabela-rolavel overflow-auto ${className}`}>
            {children}
            {paginado ? (
                <div aria-hidden="true" className="sticky bottom-0 h-2.5 bg-white" />
            ) : total !== null && (
                <div className="sticky bottom-0 px-5 py-3 border-t border-gray-100 bg-gray-50/95 text-xs text-gray-500">
                    {total === 0
                        ? 'Nenhum registro'
                        : <><strong className="text-gray-700">{total}</strong> {total === 1 ? 'registro' : 'registros'}</>}
                </div>
            )}
        </div>
    );
}

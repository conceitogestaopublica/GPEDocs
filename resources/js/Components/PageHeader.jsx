import { usePage } from '@inertiajs/react';
import FavoritarRotina from './FavoritarRotina';
import { ROTINAS, rotinaDaUrl } from '../menus';

/**
 * Cabeçalho da tela no padrão dos sistemas GPE (gpe2 e Tributário): trilha acima e, num
 * card branco, o ícone da rotina num quadrado ciano, título, subtítulo e ações à direita.
 *
 * Ícone e trilha vêm do menu (menus.js), então as telas não precisam informar nada.
 * Detalhe/formulário herdam da rotina-mãe pelo caminho (/processos/12 → Processos).
 */
function rotinaDaTela(url) {
    const exata = rotinaDaUrl(url);
    if (exata) return { rotina: exata, propria: true };
    const caminho = url.split('?')[0];
    let melhor = null;
    for (const r of ROTINAS) {
        if (r.href.includes('?') || !caminho.startsWith(r.href + '/')) continue;
        if (!melhor || r.href.length > melhor.href.length) melhor = r;
    }
    return { rotina: melhor, propria: false };
}

export default function PageHeader({ title, subtitle, icon, children }) {
    const { url } = usePage();
    const { rotina, propria } = rotinaDaTela(url);
    const trilha = rotina
        ? [rotina.modulo, rotina.grupo, propria ? null : rotina.label].filter(Boolean)
        : [];

    return (
        <div className="mb-4">
            {trilha.length > 0 && (
                <nav aria-label="Trilha" className="flex flex-wrap items-center gap-1.5 text-sm text-navy-700/80 mb-3 px-1">
                    {trilha.map((t, i) => (
                        <span key={i} className="flex items-center gap-1.5">
                            {i > 0 && <i className="fas fa-chevron-right text-[9px] text-navy-700/40" />}
                            {t}
                        </span>
                    ))}
                    <i className="fas fa-chevron-right text-[9px] text-navy-700/40" />
                    <span className="font-semibold text-navy-900">{title}</span>
                </nav>
            )}

            <div className="ds-page-header">
                <div className="flex items-center gap-4 min-w-0">
                    <span className="flex w-12 h-12 shrink-0 items-center justify-center rounded-xl bg-primary-50 text-primary-600">
                        <i className={`${icon || rotina?.icon || 'fas fa-file-alt'} text-lg`} />
                    </span>
                    <div className="min-w-0">
                        {/* Estrela ao lado do título: favorita a rotina (só aparece em rotina do menu). */}
                        <div className="flex items-center gap-1.5">
                            <h1>{title}</h1>
                            <FavoritarRotina />
                        </div>
                        {subtitle && <p>{subtitle}</p>}
                    </div>
                </div>
                {children && <div className="flex flex-wrap items-center gap-2">{children}</div>}
            </div>
        </div>
    );
}

import { useId } from 'react';

/**
 * ModuloIcon — ícone dos módulos no estilo "GPE Cloud": círculo com gradiente, folha
 * branca com canto dobrado e o nome do módulo ("Docs", "Flow"...) dentro da folha.
 *
 * Cada módulo tem o seu tom (mesma família de cor que ele usa no sistema); texto sem
 * tom definido cai no turquesa da marca. O nome é desenhado com largura travada
 * (textLength) para sempre caber na folha, em qualquer tamanho.
 *
 * Props:
 *   texto:     texto dentro da folha (default "Docs")
 *   size:      tamanho em px (default 64)
 *   tons:      [claro, escuro] para forçar as cores (opcional)
 *   className: classes adicionais
 */
const TONS = {
    Docs: ['#2BC4D4', '#0B8A9C'],   // turquesa da marca
    Flow: ['#34D3A6', '#0E8F6E'],   // verde-água (Flow usa teal/emerald)
    Conf: ['#8FA3BF', '#40546F'],   // azul-ardósia (Config usa slate)
};
const PADRAO = TONS.Docs;

export default function ModuloIcon({ texto = 'Docs', size = 64, tons = null, className = '' }) {
    const id = useId().replace(/:/g, '');
    const [claro, escuro] = tons || TONS[texto] || PADRAO;
    const larguraTexto = Math.min(36, (texto || '').length * 9.4);

    return (
        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100" width={size} height={size}
            className={className} role="img" aria-label={texto}>
            <defs>
                <linearGradient id={`${id}-fundo`} x1="0" y1="0" x2="1" y2="1">
                    <stop offset="0" stopColor={claro} />
                    <stop offset="1" stopColor={escuro} />
                </linearGradient>
                <filter id={`${id}-sombra`} x="-20%" y="-20%" width="140%" height="140%">
                    <feDropShadow dx="0" dy="2" stdDeviation="2" floodColor="#0F172A" floodOpacity="0.22" />
                </filter>
            </defs>

            {/* Círculo com gradiente */}
            <circle cx="50" cy="50" r="50" fill={`url(#${id}-fundo)`} />

            {/* Folha com cantos arredondados e o canto superior direito dobrado */}
            <g filter={`url(#${id}-sombra)`}>
                <path d="M33 18 H60 L73 31 V78 Q73 82 69 82 H33 Q29 82 29 78 V22 Q29 18 33 18 Z" fill="#FFFFFF" />
            </g>
            <path d="M60 18 V28 Q60 31 63 31 H73 Z" fill={claro} opacity="0.45" />

            {/* Linhas de "texto" do documento */}
            <rect x="36" y="33" width="16" height="3.4" rx="1.7" fill={escuro} opacity="0.28" />
            <rect x="36" y="40.5" width="28" height="3.4" rx="1.7" fill={escuro} opacity="0.16" />

            {/* Nome do módulo */}
            <text x="51" y="68" textAnchor="middle"
                fontFamily="Inter, ui-sans-serif, system-ui, sans-serif" fontWeight="800" fontSize="17"
                textLength={larguraTexto} lengthAdjust="spacingAndGlyphs" fill={escuro}>
                {texto}
            </text>
        </svg>
    );
}

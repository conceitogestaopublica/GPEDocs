/**
 * ModuloIcon — icone SVG estilo "GPE Cloud" usado nos modulos do sistema.
 *
 * Reproduz o desenho do icone do GPE Cloud: circulo turquesa, pagina
 * branca com canto dobrado e o texto customizavel ("Docs", "Flow", etc.)
 * na cor turquesa.
 *
 * Props:
 *   texto: string que aparece dentro da pagina (default "Docs")
 *   size:  tamanho em px (default 64)
 *   className: classes adicionais
 */
export default function ModuloIcon({ texto = 'Docs', size = 64, className = '' }) {
    const turquesa = '#14ACBC';

    return (
        <svg
            xmlns="http://www.w3.org/2000/svg"
            viewBox="0 0 100 100"
            width={size}
            height={size}
            className={className}
            aria-label={texto}
        >
            {/* Circulo turquesa */}
            <circle cx="50" cy="50" r="50" fill={turquesa} />

            {/* Pagina branca com canto superior direito dobrado */}
            <path
                d="M 28 24 L 64 24 L 76 36 L 76 80 L 28 80 Z"
                fill="white"
            />
            {/* Canto dobrado (sombra triangular do dobramento) */}
            <path
                d="M 64 24 L 76 36 L 64 36 Z"
                fill="#1A2933"
            />
            <path
                d="M 64 24 L 64 36 L 76 36 Z"
                fill="#33424B"
                opacity="0.6"
            />

            {/* Texto centrado na pagina */}
            <text
                x="52"
                y="68"
                textAnchor="middle"
                fontFamily="Georgia, 'Times New Roman', serif"
                fontWeight="700"
                fontSize={textoToFontSize(texto)}
                fill={turquesa}
                fontStyle="italic"
            >
                {texto}
            </text>
        </svg>
    );
}

function textoToFontSize(texto) {
    // Ajusta tamanho conforme comprimento do texto para caber na pagina
    const len = (texto || '').length;
    if (len <= 3) return 24;
    if (len <= 4) return 22;
    if (len <= 5) return 18;
    return 16;
}

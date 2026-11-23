/**
 * Marca: o símbolo novo (cubo-documento ou família) + exatamente o texto da GPE Cloud
 * ("GPECloud — Gestão Pública Eficiente"), recortado do logo oficial em public/images/logo-gpe-cloud-texto.png.
 *
 *   <LogoGpeDocs />                       horizontal, com "Gestão Pública Eficiente"
 *   <LogoGpeDocs subtitulo={false} />     só "GPECloud"
 *   <LogoGpeDocs variante="invertida" />  sobre fundo escuro
 *   <LogoGpeDocs variante="familia" />    aglomerado de cubos da família (login, divulgação; ≥ 48 px)
 */
export default function LogoGpeDocs({ tamanho = 56, subtitulo = true, variante = 'cor', className = '' }) {
    const escuro = variante === 'invertida';
    const icone = {
        invertida: '/images/logo-gpe-docs-icon-invertida.svg',
        familia: '/images/logo-gpe-docs-familia.svg',
    }[variante] ?? '/images/logo-gpe-docs-icon.svg';

    return (
        <div className={`inline-flex items-center gap-3 select-none ${className}`}>
            <img src={icone} alt="" aria-hidden="true" style={{ width: tamanho, height: tamanho }} />
            {escuro ? (
                // Sobre fundo escuro o texto da GPE Cloud (azul-marinho) some: aqui fica o nome em texto.
                <span className="whitespace-nowrap font-extrabold text-white" style={{ fontSize: Math.round(tamanho * 0.5) }}>GPE Cloud</span>
            ) : (
                // Exatamente o texto da marca GPE Cloud, recortado do logo oficial (logo-gpe-cloud-full.png).
                // Sem subtítulo: mostra só a faixa de cima da imagem ("GPECloud" ocupa ~55% da altura).
                <div style={{ height: subtitulo ? 'auto' : tamanho * 0.6, overflow: 'hidden' }}>
                    <img src="/images/logo-gpe-cloud-texto.png" alt={subtitulo ? 'GPE Cloud — Gestão Pública Eficiente' : 'GPE Cloud'}
                        style={{ height: subtitulo ? tamanho * 0.95 : tamanho * 0.6 / 0.55, width: 'auto', maxWidth: 'none' }} />
                </div>
            )}
        </div>
    );
}

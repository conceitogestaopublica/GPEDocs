/**
 * Foto do servidor ou, sem foto, as iniciais do nome (TR 4.7.14).
 * `usuario` é o objeto vindo do servidor ({ name, foto_url }); `nome` serve
 * quando só há o nome gravado (ex.: remetente_nome).
 */
const TAMANHOS = { xs: 'w-5 h-5 text-[8px]', sm: 'w-7 h-7 text-[10px]', md: 'w-9 h-9 text-xs', lg: 'w-12 h-12 text-sm' };

export default function Avatar({ usuario, nome, tamanho = 'sm', className = '' }) {
    const rotulo = usuario?.name || nome || '';
    const classe = `${TAMANHOS[tamanho] || TAMANHOS.sm} rounded-full shrink-0 ${className}`;

    if (usuario?.foto_url) {
        return <img src={usuario.foto_url} alt={rotulo} title={rotulo} className={`${classe} object-cover`} loading="lazy" />;
    }

    const iniciais = rotulo.split(' ').filter(Boolean).filter((p) => p.length > 2 || p === p.toUpperCase()).slice(0, 2)
        .map((p) => p[0]).join('').toUpperCase() || '?';

    return (
        <span title={rotulo} className={`${classe} inline-flex items-center justify-center font-bold text-white bg-gradient-to-br from-cyan-500 to-blue-600`}>
            {iniciais}
        </span>
    );
}

/**
 * Documento emitido em nome de alguém por contrassenha: mostra autorizador e
 * digitador (TR 4.18.3). Nada aparece em documento comum.
 */
export default function EmitidoPorContrassenha({ documento }) {
    if (!documento?.digitador) return null;
    return (
        <div className="mb-4 flex items-start gap-2 text-sm bg-amber-50 border border-amber-200 text-amber-900 rounded-xl px-4 py-3">
            <i className="fas fa-user-tie mt-0.5" />
            <span>
                Emitido em nome de <b>{documento.remetente?.name || 'autorizador'}</b> — digitado por <b>{documento.digitador.name}</b>, com contrassenha.
            </span>
        </div>
    );
}

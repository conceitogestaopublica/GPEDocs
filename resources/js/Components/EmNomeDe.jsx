/**
 * "Enviar em nome de outro servidor" por contrassenha (TR 4.18) — nas telas de
 * criação de ofício, memorando e circular. Só aparece para quem recebeu
 * alguma contrassenha vigente. Controla data.em_nome_de e data.contrassenha.
 */
export default function EmNomeDe({ autorizacoes = [], data, setData, errors = {} }) {
    if (autorizacoes.length === 0) return null;
    const ativo = !!data.em_nome_de;
    const escolhido = autorizacoes.find((a) => String(a.id) === String(data.em_nome_de));

    return (
        <div className={`rounded-xl border p-4 space-y-3 ${ativo ? 'border-amber-300 bg-amber-50' : 'border-gray-200 bg-gray-50'}`}>
            <label className="flex items-center gap-2 text-sm font-medium text-gray-800 cursor-pointer">
                <input type="checkbox" checked={ativo} className="rounded border-gray-300 text-amber-600"
                    onChange={(e) => { setData('em_nome_de', e.target.checked ? String(autorizacoes[0].id) : ''); setData('contrassenha', ''); }} />
                <i className="fas fa-user-tie text-amber-600" /> Enviar em nome de outro servidor (contrassenha)
            </label>
            {ativo && (
                <div className="grid grid-cols-1 md:grid-cols-2 gap-3">
                    <div>
                        <label className="block text-xs font-medium text-gray-600 mb-1">Em nome de</label>
                        <select value={data.em_nome_de} onChange={(e) => setData('em_nome_de', e.target.value)} className="ds-input">
                            {autorizacoes.map((a) => <option key={a.id} value={a.id}>{a.nome}</option>)}
                        </select>
                        {escolhido && <p className="text-[11px] text-gray-500 mt-1">{escolhido.restantes} uso(s) restante(s), até {escolhido.ate}</p>}
                        {errors.em_nome_de && <p className="text-xs text-red-600 mt-1">{errors.em_nome_de}</p>}
                    </div>
                    <div>
                        <label className="block text-xs font-medium text-gray-600 mb-1">Contrassenha</label>
                        <input value={data.contrassenha} onChange={(e) => setData('contrassenha', e.target.value.toUpperCase())}
                            className="ds-input font-mono tracking-widest" placeholder="XXXX-XXXX" maxLength={9} autoComplete="off" />
                        {errors.contrassenha && <p className="text-xs text-red-600 mt-1">{errors.contrassenha}</p>}
                    </div>
                    <p className="md:col-span-2 text-[11px] text-amber-800">
                        O documento sai com {escolhido?.nome || 'o autorizador'} como remetente e registra você como quem digitou.
                    </p>
                </div>
            )}
        </div>
    );
}

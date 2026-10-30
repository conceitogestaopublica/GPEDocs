/**
 * Predefinições das confirmações mais repetidas (objeto para confirmar() do useConfirm).
 * Uma regra de aparência (tom, rótulo) muda aqui, não em cada tela.
 *
 *   await confirmar(exclusao('Excluir este fluxo?'))
 *   await confirmar(arquivamento('este ofício'))
 */
export const exclusao = (titulo, descricao = null) => ({ titulo, descricao, tom: 'perigo', rotuloConfirmar: 'Excluir' });

export const arquivamento = (oQue) => ({ titulo: `Arquivar ${oQue}?`, rotuloConfirmar: 'Arquivar' });

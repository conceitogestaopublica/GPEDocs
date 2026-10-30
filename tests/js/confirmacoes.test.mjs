// Testes de resources/js/utils/confirmacoes.js (node --test). Rodar: npm run test:js
import { test } from 'node:test';
import assert from 'node:assert/strict';
import { exclusao, arquivamento } from '../../resources/js/utils/confirmacoes.js';

test('exclusão: tom de perigo e rótulo Excluir', () => {
    assert.deepEqual(exclusao('Excluir este fluxo?'), { titulo: 'Excluir este fluxo?', descricao: null, tom: 'perigo', rotuloConfirmar: 'Excluir' });
    assert.equal(exclusao('X?', 'Sem volta.').descricao, 'Sem volta.');
});

test('arquivamento: título montado e rótulo Arquivar', () => {
    assert.deepEqual(arquivamento('este ofício'), { titulo: 'Arquivar este ofício?', rotuloConfirmar: 'Arquivar' });
});

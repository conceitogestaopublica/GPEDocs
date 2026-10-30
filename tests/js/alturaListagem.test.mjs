// Testes de resources/js/utils/alturaListagem.js (node --test). Rodar: npm run test:js
import { test } from 'node:test';
import assert from 'node:assert/strict';
import { alturaDisponivel, semExcesso } from '../../resources/js/utils/alturaListagem.js';

test('altura = tela - topo - paginação - rodapé do layout - folga', () => {
    assert.equal(alturaDisponivel({ viewport: 900, topo: 200, irmao: 50, rodapeLayout: 80, folga: 56, minima: 240 }), 514);
});

test('nunca abaixo da mínima (tela baixa ou listagem muito embaixo)', () => {
    assert.equal(alturaDisponivel({ viewport: 500, topo: 400, folga: 56, minima: 240 }), 240);
});

test('excesso é descontado, respeitando a mínima', () => {
    assert.equal(semExcesso(500, 0, 240), 500);
    assert.equal(semExcesso(500, 120, 240), 380);
    assert.equal(semExcesso(300, 200, 240), 240);
});

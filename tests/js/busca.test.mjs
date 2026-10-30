// Testes de resources/js/utils/busca.js (node --test). Rodar: npm run test:js
import { test } from 'node:test';
import assert from 'node:assert/strict';
import { norm, termosDe, trechosRealcados } from '../../resources/js/utils/busca.js';

const marcados = (texto, q) => trechosRealcados(texto, q).filter((t) => t.realce).map((t) => t.texto);

test('norm tira acento e caixa', () => {
    assert.equal(norm('Ação Ofício'), 'acao oficio');
});

test('termos vazios são descartados', () => {
    assert.deepEqual(termosDe('  Caixa   Setor '), ['caixa', 'setor']);
});

test('realça ignorando acento e mantém o texto original', () => {
    assert.deepEqual(marcados('Controle de Ofícios', 'oficio'), ['Ofício']);
    assert.deepEqual(marcados('Ações', 'acoes'), ['Ações']);
});

test('vários termos e várias ocorrências', () => {
    assert.deepEqual(marcados('Caixa Setor da Caixa', 'caixa setor'), ['Caixa', 'Setor', 'Caixa']);
});

test('sem busca (ou termo de 1 letra) não realça', () => {
    assert.deepEqual(trechosRealcados('Repositório', ''), [{ texto: 'Repositório', realce: false }]);
    assert.deepEqual(marcados('Repositório', 'r'), []);
});

test('a junção dos trechos é sempre o texto original', () => {
    const t = 'Saída (Originados)';
    assert.equal(trechosRealcados(t, 'saida orig').map((p) => p.texto).join(''), t);
});

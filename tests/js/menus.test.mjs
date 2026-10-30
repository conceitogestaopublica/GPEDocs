// Testes de caracterização de resources/js/menus.js (node --test, sem dependências).
// Rodar: npm run test:js
import { test } from 'node:test';
import assert from 'node:assert/strict';
import { rotinaDaUrl } from '../../resources/js/menus.js';

const rotulo = (url) => rotinaDaUrl(url)?.href ?? null;

test('caminho exato de uma rotina', () => {
    assert.equal(rotulo('/repositorio'), '/repositorio');
    assert.equal(rotulo('/flow/inbox-pessoal'), '/flow/inbox-pessoal');
});

test('query que não é de rotina (paginação) continua na rotina do caminho', () => {
    assert.equal(rotulo('/repositorio?page=2'), '/repositorio');
});

test('href com query casa exato e com parâmetros a mais; o mais específico vence', () => {
    assert.equal(rotulo('/repositorio?filtro=favoritos'), '/repositorio?filtro=favoritos');
    assert.equal(rotulo('/repositorio?filtro=favoritos&page=3'), '/repositorio?filtro=favoritos');
});

test('tela de detalhe/formulário não é rotina', () => {
    assert.equal(rotulo('/processos/12'), null);
    assert.equal(rotulo('/documentos/5'), null);
});

test('URL desconhecida não é rotina', () => {
    assert.equal(rotulo('/nao-existe'), null);
});

import { casaHref } from '../../resources/js/menus.js';

test('casaHref: caminho, query exata e query com parâmetros a mais', () => {
    assert.equal(casaHref('/repositorio', '/repositorio?page=2'), true);
    assert.equal(casaHref('/repositorio', '/repositorio/5'), false);
    assert.equal(casaHref('/repositorio?filtro=favoritos', '/repositorio?filtro=favoritos&page=2'), true);
    assert.equal(casaHref('/repositorio?filtro=favoritos', '/repositorio?filtro=recentes'), false);
});

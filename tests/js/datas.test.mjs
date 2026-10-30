// Testes de resources/js/utils/datas.js (node --test). Rodar: npm run test:js
import { test } from 'node:test';
import assert from 'node:assert/strict';
import { haQuantoTempo } from '../../resources/js/utils/datas.js';

const AGORA = new Date('2026-10-30T12:00:00Z').getTime();
const atras = (seg) => new Date(AGORA - seg * 1000).toISOString();

test('menos de um minuto é "agora" (inclusive data no futuro)', () => {
    assert.equal(haQuantoTempo(atras(10), AGORA), 'agora');
    assert.equal(haQuantoTempo(atras(-30), AGORA), 'agora');
});

test('minutos e horas', () => {
    assert.equal(haQuantoTempo(atras(5 * 60), AGORA), 'há 5 min');
    assert.equal(haQuantoTempo(atras(3 * 3600), AGORA), 'há 3 h');
});

test('de 24 a 48 h é "ontem"; depois, dias', () => {
    assert.equal(haQuantoTempo(atras(30 * 3600), AGORA), 'ontem');
    assert.equal(haQuantoTempo(atras(4 * 86400), AGORA), 'há 4 dias');
});

test('uma semana ou mais vira a data', () => {
    assert.equal(haQuantoTempo(atras(10 * 86400), AGORA), new Date(atras(10 * 86400)).toLocaleDateString('pt-BR'));
});

test('sem data, texto vazio', () => {
    assert.equal(haQuantoTempo(null, AGORA), '');
});

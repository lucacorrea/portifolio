import test from 'node:test';
import assert from 'node:assert/strict';
import { decimalBR, cents, lineTotal, parseTableWords } from '../assets/js/orcamentos-table.js';
const word = (text, x, y, width = 50) => ({ text, confidence: 95, bbox: { x0: x, x1: x + width, y0: y, y1: y + 20 } });
const header = [word('ITEM', 50, 50), word('DESCRIÇÃO', 150, 50, 180), word('QUANT.', 550, 50), word('UNIT', 680, 50), word('TOTAL', 820, 50)];
test('valores brasileiros e frações', () => {
    assert.equal(decimalBR('R$ 1.234,56'), '1234.56'); assert.equal(cents('449,86'), 44986);
    assert.equal(lineTotal('2', '449,86'), 89972); assert.equal(lineTotal('1,25', '10,10'), 1263);
    assert.equal(decimalBR('1,234'), ''); assert.equal(decimalBR('2abc'), ''); assert.equal(decimalBR('-1'), '');
});
test('código de catálogo, descrição multilinha, sem unidade e total separado', () => {
    const result = parseTableWords([...header, word('244', 50, 120), word('Carga de gás', 150, 100, 240),
        word('com correção de vazamento', 150, 130, 300), word('2', 550, 120), word('R$449,86', 680, 120, 95),
        word('R$899,72', 820, 120, 95), word('VALOR TOTAL', 150, 200, 200), word('R$899,72', 820, 200, 95)]);
    assert.equal(result.items.length, 1); assert.equal(result.items[0].codigo, '244');
    assert.equal(result.items[0].produto, 'Carga de gás com correção de vazamento');
    assert.equal(result.items[0].unidade, ''); assert.equal(result.items[0].quantidade, '2');
    assert.equal(result.items[0].valor_unitario, '449.86'); assert.equal(result.total, '899.72');
});
test('dois itens permanecem separados e sem numeração sequencial', () => {
    const result = parseTableWords([...header, word('18', 50, 120), word('Motor', 150, 120), word('2', 550, 120), word('10,00', 680, 120), word('20,00', 820, 120),
        word('244', 50, 200), word('Carga', 150, 200), word('3', 550, 200), word('5,00', 680, 200), word('15,00', 820, 200),
        word('VALOR TOTAL', 150, 260, 200), word('35,00', 820, 260)]);
    assert.deepEqual(result.items.map(i => i.codigo), ['18', '244']); assert.equal(result.total, '35.00');
});
test('ausência de tabela gera falha explícita', () => assert.throws(() => parseTableWords([word('Assinatura', 50, 80)]), /Cabeçalho/));

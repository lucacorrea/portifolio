<?php
// Valores canônicos: ponto decimal, até duas casas, sem separador de milhar.
function lote_decimal($value, int $max, string $label): int {
    if (!is_string($value) || !preg_match('/^\d{1,13}(?:\.\d{1,2})?$/D', $value)) {
        throw new DomainException("{$label}: informe um número com até duas casas decimais.");
    }
    $parts = explode('.', $value);
    $digits = ltrim($parts[0] . str_pad($parts[1] ?? '', 2, '0'), '0');
    if (strlen($digits) > strlen((string)$max) || (int)$digits > $max) {
        throw new DomainException("{$label}: valor acima do limite permitido.");
    }
    return (int)$digits;
}

function lote_decimal_string(int $value): string {
    return intdiv($value, 100) . '.' . str_pad((string)($value % 100), 2, '0', STR_PAD_LEFT);
}

function lote_text($value, int $max, string $label, bool $required = true): string {
    if (!is_string($value)) throw new DomainException("{$label}: texto inválido.");
    $value = trim(preg_replace('/\s+/u', ' ', $value) ?? '');
    if (($required && $value === '') || mb_strlen($value, 'UTF-8') > $max || preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F]/', $value)) {
        throw new DomainException("{$label}: preencha até {$max} caracteres.");
    }
    return $value;
}

function lote_validate(array $data): array {
    foreach (['token', 'lote_token'] as $key) {
        if (!is_string($data[$key] ?? null) || !preg_match('/^[a-f0-9]{32}$/D', $data[$key])) {
            throw new DomainException('Identificador de importação inválido. Recarregue a página.');
        }
    }
    if (($data['confirmado'] ?? false) !== true) throw new DomainException('Confira o documento antes de continuar.');
    if (!in_array($data['modo'] ?? '', ['enviar', 'aprovar'], true)) throw new DomainException('Operação inválida.');
    foreach (['secretaria_id', 'fornecedor_id'] as $key) {
        if (!is_int($data[$key] ?? null) || $data[$key] <= 0) throw new DomainException('Selecione secretaria e fornecedor.');
    }
    $data['local'] = lote_text($data['local'] ?? '', 150, 'Local');
    $data['justificativa'] = lote_text($data['justificativa'] ?? '', 5000, 'Justificativa');
    if (!is_array($data['itens'] ?? null) || count($data['itens']) < 1 || count($data['itens']) > 200) {
        throw new DomainException('Cada orçamento deve conter entre 1 e 200 itens.');
    }
    $items = [];
    $total = 0;
    foreach ($data['itens'] as $i => $item) {
        if (!is_array($item)) throw new DomainException('Item inválido.');
        $label = 'Item ' . ($i + 1);
        $qty = lote_decimal($item['quantidade'] ?? null, 9999999999, "{$label}: quantidade");
        $price = lote_decimal($item['valor_unitario'] ?? null, 999999999999999, "{$label}: preço");
        if ($qty <= 0 || $price <= 0) throw new DomainException("{$label}: quantidade e preço devem ser maiores que zero.");
        if ($price > intdiv(PHP_INT_MAX - 50, $qty)) throw new DomainException("{$label}: valor acima do limite de cálculo.");
        $line = intdiv($qty * $price + 50, 100);
        if ($line <= 0) throw new DomainException("{$label}: o total deve ser maior que zero.");
        $declared = lote_decimal($item['valor_total'] ?? null, 999999999999999, "{$label}: total");
        if ($declared !== $line) throw new DomainException("{$label}: quantidade × preço difere do total informado.");
        $total += $line;
        if ($total > 999999999999999) throw new DomainException('Total acima do limite permitido.');
        $items[] = [
            'codigo' => lote_text($item['codigo'] ?? '', 50, "{$label}: referência", false),
            'produto' => lote_text($item['produto'] ?? '', 255, "{$label}: descrição"),
            'unidade' => lote_text($item['unidade'] ?? '', 20, "{$label}: unidade"),
            'quantidade' => lote_decimal_string($qty),
            'valor_unitario' => lote_decimal_string($price),
            'valor_total' => lote_decimal_string($line),
        ];
    }
    if (lote_decimal($data['total_documento'] ?? null, 999999999999999, 'Total do orçamento') !== $total) {
        throw new DomainException('A soma dos itens difere do total do orçamento. Confira o PDF.');
    }
    $data['itens'] = $items;
    $data['total'] = lote_decimal_string($total);
    return $data;
}

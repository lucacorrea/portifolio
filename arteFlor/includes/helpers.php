<?php
require_once __DIR__ . '/database.php';

function load_json(string $file): array
{
    $path = __DIR__ . '/../assets/data/' . $file;
    if (!file_exists($path)) {
        return [];
    }

    $content = file_get_contents($path);
    $data = json_decode($content, true);

    return is_array($data) ? $data : [];
}

function money_br(float|int $value): string
{
    return 'R$ ' . number_format((float) $value, 2, ',', '.');
}

function whatsapp_url(string $message): string
{
    return 'https://wa.me/' . WHATSAPP_NUMBER . '?text=' . rawurlencode($message);
}

function e(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

function first_image(array $item): string
{
    $images = $item['imagens'] ?? [];
    return is_array($images) && !empty($images[0]) ? (string) $images[0] : '';
}

function effective_price(array $produto): float
{
    $promo = (float) ($produto['preco_promocional'] ?? 0);
    $price = (float) ($produto['preco'] ?? 0);

    return $promo > 0 ? $promo : $price;
}

function status_label(string $status): string
{
    return match ($status) {
        'disponivel' => 'Disponível',
        'sob_encomenda' => 'Sob encomenda',
        'inativo' => 'Inativo',
        default => ucfirst(str_replace('_', ' ', $status)),
    };
}

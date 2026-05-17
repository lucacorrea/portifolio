<?php
require_once __DIR__ . '/config.php';

function load_json($file)
{
    $path = __DIR__ . '/../assets/data/' . $file;
    if (!is_file($path)) {
        return [];
    }

    $content = file_get_contents($path);
    $data = json_decode($content, true);

    return is_array($data) ? $data : [];
}

function money_br($value)
{
    return 'R$ ' . number_format((float) $value, 2, ',', '.');
}

function whatsapp_url($message)
{
    return 'https://wa.me/' . WHATSAPP_NUMBER . '?text=' . rawurlencode($message);
}

function e($value)
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function product_price($produto)
{
    $promotional = (float) ($produto['preco_promocional'] ?? 0);
    return $promotional > 0 ? $promotional : (float) ($produto['preco'] ?? 0);
}

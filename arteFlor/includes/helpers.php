<?php
require_once __DIR__ . '/config.php';

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

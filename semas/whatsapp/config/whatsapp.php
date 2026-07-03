<?php
declare(strict_types=1);

$host = semas_whatsapp_env('WHATSAPP_BRIDGE_HOST', '127.0.0.1');
$port = semas_whatsapp_env_int('WHATSAPP_BRIDGE_PORT', 3091);
$bridgeUrl = semas_whatsapp_env('WHATSAPP_BRIDGE_URL');

if ($bridgeUrl === null || trim($bridgeUrl) === '') {
    $bridgeUrl = 'http://' . $host . ':' . $port;
}

return [
    'api_name' => 'WhatsApp Bridge Baileys SEMAS',
    'instance_id' => semas_whatsapp_env('WHATSAPP_INSTANCE_ID', 'semas_whatsapp'),
    'bridge_host' => $host,
    'bridge_port' => $port,
    'bridge_base_url' => rtrim($bridgeUrl, '/'),
    'internal_key' => semas_whatsapp_env('WHATSAPP_INTERNAL_KEY', ''),
    'webhook_secret' => semas_whatsapp_env('WHATSAPP_WEBHOOK_SECRET', ''),
    'webhook_url' => semas_whatsapp_env('WHATSAPP_WEBHOOK_URL', ''),
    'session_path' => semas_whatsapp_env('WHATSAPP_SESSION_PATH', 'bridge/storage/sessions'),
    'log_path' => semas_whatsapp_env('WHATSAPP_LOG_PATH', 'bridge/logs'),
    'timeout' => 15,
    'message_limit' => 3000,
    'queue_batch_size' => semas_whatsapp_env_int('QUEUE_BATCH_SIZE', 10),
    'queue_max_attempts' => semas_whatsapp_env_int('QUEUE_MAX_ATTEMPTS', 3),
    'queue_interval_seconds' => semas_whatsapp_env_int('QUEUE_INTERVAL_SECONDS', 60),
    'supports' => [
        'text' => true,
        'image' => false,
        'document' => false,
        'location' => false,
        'qrcode' => true,
        'pairing_code' => true,
        'status' => true,
    ],
];

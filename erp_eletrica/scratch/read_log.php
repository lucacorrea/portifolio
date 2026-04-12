<?php
$root = __DIR__;
$logPath = $root . '/storage/last_sefaz_response.xml';
if (file_exists($logPath)) {
    echo "--- LOG CONTENT ---\n";
    echo file_get_contents($logPath);
} else {
    echo "Log file not found at: $logPath\n";
    // Tenta em outros lugares
    $paths = [
        $root . '/last_sefaz_response.xml',
        $root . '/src/App/Services/last_sefaz_response.xml'
    ];
    foreach($paths as $p) {
        if (file_exists($p)) {
            echo "--- LOG CONTENT (Found at $p) ---\n";
            echo file_get_contents($p);
            exit;
        }
    }
}

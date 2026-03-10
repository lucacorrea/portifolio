<?php
require_once 'config.php';

echo "<h1>🛠️ Diagnóstico de Ambiente - ERP Elétrica</h1>";

// 1. Verificar Extensões
$extensions = ['curl', 'openssl', 'dom', 'simplexml', 'zlib', 'pdo_mysql'];
echo "<h3>1. Verificando Extensões PHP</h3><ul>";
foreach ($extensions as $ext) {
    $status = extension_loaded($ext) ? "<span style='color:green;'>✅ OK</span>" : "<span style='color:red;'>❌ AUSENTE</span>";
    echo "<li><strong>$ext:</strong> $status</li>";
}
echo "</ul>";

// 2. Verificar Pastas
$dirs = [
    'storage' => __DIR__ . '/storage',
    'certificados' => __DIR__ . '/storage/certificados',
];
echo "<h3>2. Verificando Diretórios</h3><ul>";
foreach ($dirs as $name => $path) {
    if (!is_dir($path)) {
        echo "<li><strong>$name:</strong> <span style='color:red;'>❌ NÃO ENCONTRADO</span> ($path)</li>";
    } else {
        $writable = is_writable($path) ? "<span style='color:green;'>✅ ESCRITA OK</span>" : "<span style='color:orange;'>⚠️ SEM PERMISSÃO DE ESCRITA</span>";
        echo "<li><strong>$name:</strong> <span style='color:green;'>✅ OK</span> - $writable</li>";
    }
}
echo "</ul>";

// 3. Verificar Configuração SEFAZ Global
echo "<h3>3. Configuração SEFAZ no Banco</h3>";
try {
    $db = \App\Config\Database::getInstance()->getConnection();
    $stmt = $db->query("SELECT * FROM sefaz_config LIMIT 1");
    $config = $stmt->fetch();
    if ($config) {
        echo "<table border='1' cellpadding='5' style='border-collapse: collapse;'>";
        foreach ($config as $k => $v) {
            if ($k === 'certificado_senha') $v = '******** (Base64)';
            echo "<tr><td><strong>$k</strong></td><td>$v</td></tr>";
        }
        echo "</table>";
        
        $fullPath = __DIR__ . "/storage/certificados/" . $config['certificado_path'];
        if (file_exists($fullPath)) {
            echo "<p style='color:green;'>✅ Arquivo PFX encontrado fisicamente.</p>";
            $content = file_get_contents($fullPath);
            $certs = [];
            $password = base64_decode($config['certificado_senha']);
            if (openssl_pkcs12_read($content, $certs, $password)) {
                echo "<p style='color:green;'>✅ Senha do Certificado validada com sucesso.</p>";
            } else {
                echo "<p style='color:red;'>❌ Falha ao ler PFX com a senha salva. Verifique se a senha está correta.</p>";
            }
        } else {
            echo "<p style='color:red;'>❌ Arquivo PFX NÃO encontrado no caminho: $fullPath</p>";
        }
    } else {
        echo "<p style='color:orange;'>⚠️ Nenhuma configuração SEFAZ encontrada na tabela 'sefaz_config'.</p>";
    }
} catch (Exception $e) {
    echo "<p style='color:red;'>❌ Erro ao consultar banco: " . $e->getMessage() . "</p>";
}

echo "<h3>4. Logs de Erro Recentes</h3>";
$requestFile = __DIR__ . '/storage/last_sefaz_request.xml';
$responseFile = __DIR__ . '/storage/last_sefaz_error_response.txt';

if (file_exists($requestFile)) {
    echo "<p>Última Requisição Enviada: <a href='storage/last_sefaz_request.xml' target='_blank'>Ver XML</a></p>";
}
if (file_exists($responseFile)) {
    echo "<p>Último Erro Retornado: <pre style='background:#eee; padding:10px;'>" . htmlspecialchars(file_get_contents($responseFile)) . "</pre></p>";
}

echo "<hr><p>Remova este arquivo (diagnostico.php) após o uso por segurança.</p>";

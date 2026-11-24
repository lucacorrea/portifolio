<?php
/**
 * Diagnóstico somente-leitura do MESMO conector usado pelo login e pelos módulos.
 * Não recebe nem divulga senhas. Após homologação, retire do diretório público.
 */
declare(strict_types=1);
require dirname(__DIR__) . '/app/auth.php';
// Depois da instalação, resultados operacionais do servidor são exclusivos
// do suporte autenticado. Antes de instalar, mostra apenas diagnóstico inicial.
if (obras_arquivo_conexao() !== null) {
    try {
        $diagUser=user();
        if (!$diagUser || $diagUser['role'] !== 'suporte') {
            http_response_code(403);
            exit('Diagnóstico restrito ao suporte autenticado.');
        }
    } catch (Throwable $e) {
        http_response_code(403);
        exit('Diagnóstico indisponível sem autenticação de suporte.');
    }
}
header('X-Robots-Tag: noindex, nofollow, noarchive');
$checks = [];
$add = static function (string $name, bool $ok, string $detail = '') use (&$checks): void {
    $checks[] = ['name' => $name, 'ok' => $ok, 'detail' => $detail];
};
$add('PHP 8.2+', PHP_VERSION_ID >= 80200);
$add('Extensão PDO', extension_loaded('PDO'));
$add('Driver pdo_mysql', extension_loaded('pdo_mysql'));
$add('Hash de senha Argon2id', defined('PASSWORD_ARGON2ID'));
$add('Extensão fileinfo', extension_loaded('fileinfo'));
$add('Extensão OpenSSL', extension_loaded('openssl'));
$file = obras_arquivo_conexao();
$add('Configuração PHP privada encontrada', $file !== null,
    $file === null ? 'Não encontrada. Abra instalar.php para configurar o banco.' : 'Arquivo acessível; credenciais protegidas.');
$connected = false;
$code = '';
if ($file !== null && extension_loaded('pdo_mysql')) {
    try {
        $pdo = db();
        $connected = ((int)$pdo->query('SELECT 1')->fetchColumn() === 1);
        $add('Conexão real usada pelo login', $connected,
            $connected ? 'SELECT 1 executado com sucesso.' : 'SELECT 1 não confirmou a conexão.');
        if ($connected) {
            $name = (string)$pdo->query('SELECT DATABASE()')->fetchColumn();
            $add('Banco de dados correto', $name === 'u784961086_obras');
            $expected = ['users','login_limits','sequences','records','milestones',
                'stock_movements','protocol_forwards','attachments','audit_logs'];
            $found = array_map('strtolower', $pdo->query('SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE()')->fetchAll(PDO::FETCH_COLUMN));
            $missing = array_values(array_diff($expected, $found));
            $add('Todas as nove tabelas instaladas', count($missing) === 0,
                $missing ? 'Tabelas ausentes: ' . implode(', ', $missing) : 'Todas encontradas.');
            if (!in_array('users', $missing, true)) {
                $st = $pdo->prepare("SELECT role, active, password_hash FROM users WHERE username='suporte' LIMIT 1");
                $st->execute(); $support = $st->fetch();
                $add('Usuário suporte instalado', (bool)$support);
                if ($support) {
                    $add('Perfil suporte e conta ativa', $support['role'] === 'suporte' && (int)$support['active'] === 1);
                    $add('Senha do suporte protegida por Argon2id', password_get_info($support['password_hash'])['algoName'] === 'argon2id');
                }
            }
        }
    } catch (Throwable $e) {
        $code = login_failure_code($e);
        $add('Conexão real usada pelo login', false, 'Falha identificada: ' . $code);
        error_log('[OBRAS360 DIAGNOSTICO] codigo=' . $code . ' SQLSTATE=' . (string)$e->getCode());
    }
}
function h_diag(string $text): string { return htmlspecialchars($text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
?>
<!doctype html><html lang="pt-BR"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>Diagnóstico de conexão · OBRAS360</title><link rel="stylesheet" href="assets/styles.css?v=<?= (int) (@filemtime(__DIR__ . '/assets/styles.css') ?: 1) ?>"><link rel="stylesheet" href="assets/interactive.css?v=<?= (int) (@filemtime(__DIR__ . '/assets/interactive.css') ?: 1) ?>">
<style>body{margin:0;background:#f2f7f9;font-family:Arial,sans-serif;color:#14364a}.main{max-width:760px;margin:44px auto;background:#fff;padding:32px;border-radius:18px;box-shadow:0 10px 36px #14364a17}.brand{font-weight:800;color:#079487;font-size:21px}.subtitle{font-size:14px;color:#63808e;line-height:1.65}.list{margin:24px 0;border:1px solid #dde9ed;border-radius:12px;overflow:hidden}.item{display:flex;align-items:center;gap:12px;padding:14px;border-bottom:1px solid #e6eff2}.item:last-child{border:0}.symbol{width:34px;height:34px;border-radius:10px;display:grid;place-items:center;background:#e9f8f3;color:#0b8074;font-weight:900}.item.bad .symbol{background:#fff0e9;color:#ad3828}.item strong{display:block;font-size:14px}.item small{font-size:12px;color:#68808e;line-height:1.5}.actions{display:flex;gap:12px;flex-wrap:wrap;margin-top:18px}.actions a{padding:11px 14px;background:#0d9283;border-radius:9px;color:#fff;text-decoration:none;font-weight:bold;font-size:13px}.actions a.alt{background:#e7f3f1;color:#08796f}.note{margin-top:20px;color:#77929c;font-size:12px;line-height:1.5}@media(max-width:790px){.main{margin:12px;padding:20px}}</style></head><body><main class="main"><div class="brand">◆ OBRAS 360</div><h1>Diagnóstico PHP e MySQL</h1><p class="subtitle">Esta página testa a mesma conexão central usada pelo login e por todas as páginas do sistema. Não precisa digitar a senha do banco nesta tela.</p><div class="list">
<?php foreach ($checks as $item): ?><div class="item <?= $item['ok']?'':'bad'?>"><div class="symbol"><?= $item['ok']?'✓':'!' ?></div><div><strong><?=h_diag($item['name'])?></strong><?php if ($item['detail'] !== ''): ?><small><?=h_diag($item['detail'])?></small><?php endif; ?></div></div><?php endforeach; ?></div>
<?php if ($connected && !array_filter($checks, fn($x)=>!$x['ok'])): ?><p class="subtitle"><b>Resultado: conexão e instalação confirmadas pelo PHP deste servidor.</b> O login já pode ser testado.</p><?php else: ?><p class="subtitle">Se a configuração não existir, abra o instalador. Se o banco conectar, mas faltar tabela, importe os scripts SQL antes de continuar. Se aparecer um código, consulte o guia de diagnóstico.</p><?php endif; ?>
<div class="actions"><a href="instalar.php">Instalação inicial</a><a class="alt" href="acesso.php">Ir para login</a></div><p class="note">Somente informações operacionais são exibidas. Esta página não mostra senhas nem hashes. Por segurança, exclua este diagnóstico público após os testes.</p></main></body></html>

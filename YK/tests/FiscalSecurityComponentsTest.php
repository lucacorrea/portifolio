<?php

declare(strict_types=1);

$vendorAutoload = dirname(__DIR__) . '/vendor/autoload.php';
if (is_file($vendorAutoload)) require $vendorAutoload;
require dirname(__DIR__) . '/src/Fiscal/Security/FiscalSecretVault.php';
require dirname(__DIR__) . '/src/Fiscal/Storage/FiscalCertificateStorage.php';

use App\Fiscal\Security\FiscalSecretVault;
use App\Fiscal\Storage\FiscalCertificateStorage;

function fiscalSecurityAssert(bool $condition, string $message): void
{
    if (!$condition) throw new RuntimeException($message);
}

function fiscalSecurityThrows(callable $callback, string $message): void
{
    try {
        $callback();
    } catch (Throwable) {
        return;
    }
    throw new RuntimeException($message);
}

$key = base64_encode(random_bytes(32));
$vault = new FiscalSecretVault($key, 'test-v1');
$cryptoAvailable = function_exists('sodium_crypto_aead_xchacha20poly1305_ietf_encrypt')
    || (function_exists('openssl_encrypt') && in_array('aes-256-gcm', openssl_get_cipher_methods(), true));

if ($cryptoAvailable) {
    $secret = 'senha-certificado:teste';
    $payload = $vault->seal($secret);
    fiscalSecurityAssert($vault->open($payload) === $secret, 'O vault deve abrir exatamente o segredo protegido.');
    fiscalSecurityAssert(!in_array($secret, $payload, true), 'O payload não pode conter o segredo em texto puro.');
    fiscalSecurityThrows(
        fn() => (new FiscalSecretVault($key, 'test-v2'))->open($payload),
        'Outra versão da chave não pode abrir o payload.'
    );
    $payload['ciphertext'] = base64_encode((base64_decode($payload['ciphertext'], true) ?: '') . 'x');
    fiscalSecurityThrows(fn() => $vault->open($payload), 'Ciphertext adulterado deve falhar fechado.');
} else {
    fiscalSecurityThrows(fn() => $vault->seal('segredo'), 'Sem sodium/OpenSSL, o vault deve falhar fechado.');
}

fiscalSecurityThrows(
    fn() => new FiscalSecretVault(base64_encode(random_bytes(16))),
    'A chave mestra deve ter exatamente 32 bytes.'
);
fiscalSecurityThrows(fn() => $vault->seal(''), 'Segredo vazio deve ser rejeitado.');
fiscalSecurityThrows(
    fn() => $vault->open(['ciphertext' => '*', 'nonce' => '*', 'tag' => '*', 'key_version' => 'test-v1', 'algorithm' => 'aes-256-gcm']),
    'Base64 inválido deve ser rejeitado antes da decriptação.'
);

$previousMasterKey = getenv('FISCAL_MASTER_KEY');
try {
    putenv('FISCAL_MASTER_KEY');
    fiscalSecurityThrows(fn() => FiscalSecretVault::fromEnvironment(), 'Vault sem chave externa deve falhar fechado.');
    putenv('FISCAL_MASTER_KEY=' . $key);
    fiscalSecurityAssert(FiscalSecretVault::fromEnvironment('test-v1') instanceof FiscalSecretVault, 'Vault deve aceitar a chave externa válida.');
} finally {
    $previousMasterKey === false
        ? putenv('FISCAL_MASTER_KEY')
        : putenv('FISCAL_MASTER_KEY=' . $previousMasterKey);
}

$root = str_replace('\\', '/', FiscalCertificateStorage::resolveStorageRoot('/home/user/public_html/YK'));
fiscalSecurityAssert(
    $root === '/home/user/configuracoes/yk/fiscal/certificados',
    'O certificado deve ficar fora do public_html.'
);
fiscalSecurityAssert(
    FiscalCertificateStorage::normalizeCnpj('11.222.333/0001-81') === '11222333000181',
    'O CNPJ esperado deve ser normalizado e validado.'
);
fiscalSecurityThrows(
    fn() => FiscalCertificateStorage::normalizeCnpj('11.111.111/1111-11'),
    'CNPJ inválido deve ser rejeitado.'
);

$storage = new FiscalCertificateStorage(sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'yk-fiscal-test');
fiscalSecurityAssert($storage->resolve('../certificado.p12') === null, 'Traversal de referência deve ser rejeitado.');
fiscalSecurityAssert($storage->resolve('fiscal/certificados/nao-opaco.p12') === null, 'Referência não opaca deve ser rejeitada.');
fiscalSecurityThrows(
    fn() => $storage->inspectPkcs12('conteudo-invalido', 'senha', '11.222.333/0001-81'),
    'PKCS#12 inválido ou OpenSSL indisponível deve falhar fechado.'
);
fiscalSecurityThrows(
    fn() => $storage->inspectPkcs12(str_repeat('x', 2_097_153), 'senha', '11.222.333/0001-81'),
    'Conteúdo acima de 2 MB deve ser rejeitado antes do parser.'
);

$certificateCnpj = (new ReflectionClass(FiscalCertificateStorage::class))->getMethod('certificateCnpj');
fiscalSecurityAssert(
    $certificateCnpj->invoke(
        $storage,
        'certificado-sem-oid',
        'senha',
        ['CN' => 'EMPRESA TESTE:11222333000181']
    ) === '11222333000181',
    'A leitura do CNPJ deve manter compatibilidade com o texto do titular.'
);

echo "Fiscal security component tests passed.\n";

<?php

declare(strict_types=1);

namespace App\Fiscal\Security;

use InvalidArgumentException;
use RuntimeException;

final class FiscalSecretVault
{
    private const SODIUM_ALGORITHM = 'xchacha20poly1305_ietf';
    private const OPENSSL_ALGORITHM = 'aes-256-gcm';
    private const OPENSSL_NONCE_BYTES = 12;
    private const TAG_BYTES = 16;

    private string $masterKey;

    public function __construct(string $base64MasterKey, private readonly string $keyVersion = 'v1')
    {
        if (preg_match('/^[A-Za-z0-9._-]{1,40}$/', $this->keyVersion) !== 1) {
            throw new InvalidArgumentException('Versão da chave fiscal inválida.');
        }

        $decoded = base64_decode(trim($base64MasterKey), true);
        if (!is_string($decoded) || strlen($decoded) !== 32) {
            throw new InvalidArgumentException('Chave mestra fiscal inválida.');
        }

        $this->masterKey = $decoded;
    }

    public static function fromEnvironment(string $keyVersion = 'v1'): self
    {
        $key = getenv('FISCAL_MASTER_KEY');
        if (!is_string($key) || trim($key) === '') {
            throw new RuntimeException('Chave mestra fiscal não configurada.');
        }

        return new self($key, $keyVersion);
    }

    /** @return array{ciphertext:string,nonce:string,tag:string,key_version:string,algorithm:string} */
    public function seal(string $plaintext): array
    {
        if ($plaintext === '') {
            throw new InvalidArgumentException('Segredo fiscal vazio.');
        }

        if ($this->supportsSodium()) {
            return $this->sealWithSodium($plaintext);
        }
        if ($this->supportsOpenSsl()) {
            return $this->sealWithOpenSsl($plaintext);
        }

        throw new RuntimeException('Criptografia segura indisponível para os segredos fiscais.');
    }

    /** @param array{ciphertext?:mixed,nonce?:mixed,tag?:mixed,key_version?:mixed,algorithm?:mixed} $payload */
    public function open(array $payload): string
    {
        $version = (string) ($payload['key_version'] ?? '');
        $algorithm = (string) ($payload['algorithm'] ?? '');
        if ($version !== $this->keyVersion) {
            throw new RuntimeException('Versão da chave fiscal não disponível.');
        }

        $ciphertext = $this->decode((string) ($payload['ciphertext'] ?? ''));
        $nonce = $this->decode((string) ($payload['nonce'] ?? ''));
        $tag = $this->decode((string) ($payload['tag'] ?? ''));

        return match ($algorithm) {
            self::SODIUM_ALGORITHM => $this->openWithSodium($ciphertext, $nonce, $tag),
            self::OPENSSL_ALGORITHM => $this->openWithOpenSsl($ciphertext, $nonce, $tag),
            default => throw new RuntimeException('Algoritmo do segredo fiscal inválido.'),
        };
    }

    /** @return array{ciphertext:string,nonce:string,tag:string,key_version:string,algorithm:string} */
    private function sealWithSodium(string $plaintext): array
    {
        $nonce = random_bytes(SODIUM_CRYPTO_AEAD_XCHACHA20POLY1305_IETF_NPUBBYTES);
        $encrypted = sodium_crypto_aead_xchacha20poly1305_ietf_encrypt(
            $plaintext,
            $this->additionalData(self::SODIUM_ALGORITHM),
            $nonce,
            $this->masterKey
        );
        $tag = substr($encrypted, -SODIUM_CRYPTO_AEAD_XCHACHA20POLY1305_IETF_ABYTES);
        $ciphertext = substr($encrypted, 0, -SODIUM_CRYPTO_AEAD_XCHACHA20POLY1305_IETF_ABYTES);

        return $this->payload($ciphertext, $nonce, $tag, self::SODIUM_ALGORITHM);
    }

    /** @return array{ciphertext:string,nonce:string,tag:string,key_version:string,algorithm:string} */
    private function sealWithOpenSsl(string $plaintext): array
    {
        $nonce = random_bytes(self::OPENSSL_NONCE_BYTES);
        $tag = '';
        $ciphertext = openssl_encrypt(
            $plaintext,
            'aes-256-gcm',
            $this->masterKey,
            OPENSSL_RAW_DATA,
            $nonce,
            $tag,
            $this->additionalData(self::OPENSSL_ALGORITHM),
            self::TAG_BYTES
        );
        if (!is_string($ciphertext) || strlen($tag) !== self::TAG_BYTES) {
            throw new RuntimeException('Não foi possível proteger o segredo fiscal.');
        }

        return $this->payload($ciphertext, $nonce, $tag, self::OPENSSL_ALGORITHM);
    }

    private function openWithSodium(string $ciphertext, string $nonce, string $tag): string
    {
        if (!$this->supportsSodium()
            || strlen($nonce) !== SODIUM_CRYPTO_AEAD_XCHACHA20POLY1305_IETF_NPUBBYTES
            || strlen($tag) !== SODIUM_CRYPTO_AEAD_XCHACHA20POLY1305_IETF_ABYTES
        ) {
            throw new RuntimeException('Segredo fiscal inválido.');
        }

        $plaintext = sodium_crypto_aead_xchacha20poly1305_ietf_decrypt(
            $ciphertext . $tag,
            $this->additionalData(self::SODIUM_ALGORITHM),
            $nonce,
            $this->masterKey
        );
        if (!is_string($plaintext)) {
            throw new RuntimeException('Não foi possível abrir o segredo fiscal.');
        }

        return $plaintext;
    }

    private function openWithOpenSsl(string $ciphertext, string $nonce, string $tag): string
    {
        if (!$this->supportsOpenSsl()
            || strlen($nonce) !== self::OPENSSL_NONCE_BYTES
            || strlen($tag) !== self::TAG_BYTES
        ) {
            throw new RuntimeException('Segredo fiscal inválido.');
        }

        $plaintext = openssl_decrypt(
            $ciphertext,
            'aes-256-gcm',
            $this->masterKey,
            OPENSSL_RAW_DATA,
            $nonce,
            $tag,
            $this->additionalData(self::OPENSSL_ALGORITHM)
        );
        if (!is_string($plaintext)) {
            throw new RuntimeException('Não foi possível abrir o segredo fiscal.');
        }

        return $plaintext;
    }

    /** @return array{ciphertext:string,nonce:string,tag:string,key_version:string,algorithm:string} */
    private function payload(string $ciphertext, string $nonce, string $tag, string $algorithm): array
    {
        return [
            'ciphertext' => base64_encode($ciphertext),
            'nonce' => base64_encode($nonce),
            'tag' => base64_encode($tag),
            'key_version' => $this->keyVersion,
            'algorithm' => $algorithm,
        ];
    }

    private function decode(string $value): string
    {
        $decoded = base64_decode($value, true);
        if (!is_string($decoded)) {
            throw new RuntimeException('Segredo fiscal codificado inválido.');
        }

        return $decoded;
    }

    private function additionalData(string $algorithm): string
    {
        return 'yk:fiscal-secret:' . $this->keyVersion . ':' . $algorithm;
    }

    private function supportsSodium(): bool
    {
        return function_exists('sodium_crypto_aead_xchacha20poly1305_ietf_encrypt')
            && function_exists('sodium_crypto_aead_xchacha20poly1305_ietf_decrypt');
    }

    private function supportsOpenSsl(): bool
    {
        return function_exists('openssl_encrypt')
            && function_exists('openssl_decrypt')
            && in_array('aes-256-gcm', openssl_get_cipher_methods(), true);
    }
}

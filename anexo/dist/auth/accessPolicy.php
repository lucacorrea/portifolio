<?php

declare(strict_types=1);

/**
 * /dist/auth/accessPolicy.php
 *
 * Política de rede para o perfil "comum".
 *
 * REGRA:
 * - somente o perfil "comum" é obrigado a estar em uma rede/IP autorizado;
 * - se o IP atual não estiver na lista, o acesso é negado;
 * - prefeito, secretario e admin não passam por esta restrição de rede.
 *
 * Compatível com PHP 7.2+.
 */

date_default_timezone_set('America/Manaus');


/* ============================================================
 * CONFIGURAÇÃO DA REDE
 * ============================================================ */

/**
 * Mantenha TRUE para a proteção funcionar.
 *
 * Se alterar para FALSE, usuários comuns poderão acessar
 * de qualquer rede.
 */
const COMMON_NETWORK_RESTRICTION_ENABLED = true;


/**
 * IPs públicos / redes autorizadas.
 *
 * IMPORTANTE:
 * - use o IP público visto pelo servidor;
 * - NÃO use 192.168.x.x, 10.x.x.x ou 172.16.x.x;
 * - se a lista ficar vazia, o sistema BLOQUEIA todos os comuns
 *   (comportamento seguro/fail-closed).
 *
 * Exemplos:
 *
 * const COMMON_ALLOWED_NETWORKS = [
 *     '177.85.123.40',      // IP público exato
 *     '177.85.124.0/24',    // faixa IPv4
 *     '2803:1234::/48',     // faixa IPv6
 * ];
 */

const COMMON_ALLOWED_NETWORKS = [
    '153.67.111.146',
];


/**
 * Se o site estiver atrás do Cloudflare PROXY (nuvem laranja),
 * REMOTE_ADDR normalmente será o IP do Cloudflare e não do usuário.
 *
 * Deixe FALSE por padrão.
 *
 * Só altere para TRUE se:
 * 1. o domínio estiver realmente usando proxy Cloudflare; E
 * 2. o servidor não aceitar acesso direto contornando o Cloudflare.
 */
const ACCESS_TRUST_CLOUDFLARE_IP = false;


/* ============================================================
 * IP DO CLIENTE
 * ============================================================ */

/**
 * Valida e normaliza um endereço IP.
 */
function access_valid_ip(string $ip): string
{
    $ip = trim($ip);

    if ($ip === '') {
        return '';
    }

    return filter_var($ip, FILTER_VALIDATE_IP) !== false ? $ip : '';
}


/**
 * Retorna o IP do cliente que será usado na política de acesso.
 *
 * Por padrão usamos REMOTE_ADDR, pois cabeçalhos como
 * X-Forwarded-For podem ser falsificados se não houver proxy
 * confiável configurado.
 */
function access_client_ip(): string
{
    if (ACCESS_TRUST_CLOUDFLARE_IP) {
        $cfIp = isset($_SERVER['HTTP_CF_CONNECTING_IP'])
            ? access_valid_ip((string)$_SERVER['HTTP_CF_CONNECTING_IP'])
            : '';

        if ($cfIp !== '') {
            return $cfIp;
        }
    }

    $remoteIp = isset($_SERVER['REMOTE_ADDR'])
        ? access_valid_ip((string)$_SERVER['REMOTE_ADDR'])
        : '';

    return $remoteIp;
}


/* ============================================================
 * IP / CIDR
 * ============================================================ */

/**
 * Verifica se um IP corresponde a:
 * - um IP exato; ou
 * - uma rede em CIDR.
 *
 * Compatível com IPv4 e IPv6.
 */
function access_ip_matches_rule(string $ip, string $rule): bool
{
    $ip   = access_valid_ip($ip);
    $rule = trim($rule);

    if ($ip === '' || $rule === '') {
        return false;
    }

    /* IP exato */
    if (strpos($rule, '/') === false) {
        $ruleIp = access_valid_ip($rule);

        return $ruleIp !== '' && $ip === $ruleIp;
    }

    $parts = explode('/', $rule, 2);

    if (count($parts) !== 2) {
        return false;
    }

    $network      = access_valid_ip(trim($parts[0]));
    $prefixString = trim($parts[1]);

    if ($network === '' || $prefixString === '' || !ctype_digit($prefixString)) {
        return false;
    }

    $prefixLength = (int)$prefixString;

    $ipBinary      = @inet_pton($ip);
    $networkBinary = @inet_pton($network);

    if ($ipBinary === false || $networkBinary === false) {
        return false;
    }

    /* IPv4 e IPv6 não podem ser comparados entre si. */
    if (strlen($ipBinary) !== strlen($networkBinary)) {
        return false;
    }

    $maxBits = strlen($ipBinary) * 8;

    if ($prefixLength < 0 || $prefixLength > $maxBits) {
        return false;
    }

    $fullBytes     = intdiv($prefixLength, 8);
    $remainingBits = $prefixLength % 8;

    if ($fullBytes > 0) {
        if (
            substr($ipBinary, 0, $fullBytes) !==
            substr($networkBinary, 0, $fullBytes)
        ) {
            return false;
        }
    }

    if ($remainingBits === 0) {
        return true;
    }

    $mask = (0xFF << (8 - $remainingBits)) & 0xFF;

    $ipByte      = ord($ipBinary[$fullBytes]);
    $networkByte = ord($networkBinary[$fullBytes]);

    return (($ipByte & $mask) === ($networkByte & $mask));
}


/* ============================================================
 * REDE AUTORIZADA
 * ============================================================ */

/**
 * Retorna TRUE somente se o IP estiver em uma das redes permitidas.
 *
 * Se a restrição estiver ligada e a lista estiver vazia,
 * retorna FALSE para impedir liberação acidental.
 */
function access_network_allowed(string $ip): bool
{
    if (!COMMON_NETWORK_RESTRICTION_ENABLED) {
        return true;
    }

    $ip = access_valid_ip($ip);

    if ($ip === '') {
        return false;
    }

    if (count(COMMON_ALLOWED_NETWORKS) === 0) {
        return false;
    }

    foreach (COMMON_ALLOWED_NETWORKS as $network) {
        if (access_ip_matches_rule($ip, (string)$network)) {
            return true;
        }
    }

    return false;
}


/* ============================================================
 * POLÍTICA DO USUÁRIO COMUM
 * ============================================================ */

/**
 * O perfil comum depende SOMENTE da rede autorizada nesta política.
 *
 * Retorno:
 * [
 *     'allowed' => bool,
 *     'reason'  => string,
 *     'ip'      => string
 * ]
 */
function access_check_common(): array
{
    $ip = access_client_ip();

    if (!access_network_allowed($ip)) {
        return [
            'allowed' => false,
            'reason'  => 'Rede não autorizada.',
            'ip'      => $ip
        ];
    }

    return [
        'allowed' => true,
        'reason'  => '',
        'ip'      => $ip
    ];
}

?>
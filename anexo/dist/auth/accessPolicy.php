<?php

declare(strict_types=1);

/**
 * ============================================================
 * POLÍTICA DE ACESSO
 * ============================================================
 *
 * Centraliza:
 * - horário permitido
 * - dias permitidos
 * - redes/IPs permitidos
 *
 * Compatível com PHP 7.2+
 */

date_default_timezone_set('America/Manaus');


/* ============================================================
 * CONFIGURAÇÃO DO PERFIL COMUM
 * ============================================================ */

/**
 * Horário permitido.
 */
const COMMON_ACCESS_START = '07:00';
const COMMON_ACCESS_END   = '18:00';


/**
 * Dias permitidos.
 *
 * ISO-8601:
 * 1 = Segunda
 * 2 = Terça
 * 3 = Quarta
 * 4 = Quinta
 * 5 = Sexta
 * 6 = Sábado
 * 7 = Domingo
 */
const COMMON_ALLOWED_DAYS = [
    1,
    2,
    3,
    4,
    5
];


/**
 * Ativa/desativa a restrição por rede.
 *
 * IMPORTANTE:
 * deixe TRUE para realmente restringir.
 */
const COMMON_NETWORK_RESTRICTION_ENABLED = true;


/**
 * IPs / redes autorizadas.
 *
 * Você deve substituir pelos IPs reais da Secretaria/Prefeitura.
 *
 * Pode usar:
 *
 * IP único:
 * 177.10.20.30
 *
 * Rede:
 * 177.10.20.0/24
 *
 * IPv6 também pode ser informado.
 *
 * ATENÇÃO:
 * 203.0.113.10 abaixo é apenas um IP de EXEMPLO.
 * Enquanto não trocar, usuários comuns serão bloqueados.
 */
const COMMON_ALLOWED_NETWORKS = [
    '203.0.113.10',

    // Exemplos:
    // '177.10.20.30',
    // '177.10.20.0/24',
];


/* ============================================================
 * IP DO CLIENTE
 * ============================================================ */

/**
 * Retorna o IP que realmente realizou conexão com o servidor.
 *
 * Não usamos diretamente:
 * HTTP_X_FORWARDED_FOR
 *
 * porque pode ser falsificado caso não exista proxy confiável
 * configurado.
 */
function access_client_ip(): string
{
    $ip = isset($_SERVER['REMOTE_ADDR'])
        ? trim((string)$_SERVER['REMOTE_ADDR'])
        : '';

    if ($ip === '') {
        return '';
    }

    return $ip;
}


/* ============================================================
 * IP / CIDR
 * ============================================================ */

/**
 * Verifica se um IP pertence a um IP ou CIDR.
 *
 * Funciona com:
 * IPv4
 * IPv6
 *
 * Exemplos:
 *
 * 177.10.20.30
 * 177.10.20.0/24
 */
function access_ip_matches_rule(string $ip, string $rule): bool
{
    $ip = trim($ip);
    $rule = trim($rule);

    if ($ip === '' || $rule === '') {
        return false;
    }

    /*
     * Caso seja IP exato.
     */
    if (strpos($rule, '/') === false) {
        return $ip === $rule;
    }

    $parts = explode('/', $rule, 2);

    if (count($parts) !== 2) {
        return false;
    }

    $network = trim($parts[0]);
    $prefixLength = (int)$parts[1];

    $ipBinary = @inet_pton($ip);
    $networkBinary = @inet_pton($network);

    if ($ipBinary === false || $networkBinary === false) {
        return false;
    }

    /*
     * IPv4 e IPv6 não podem ser comparados entre si.
     */
    if (strlen($ipBinary) !== strlen($networkBinary)) {
        return false;
    }

    $maxBits = strlen($ipBinary) * 8;

    if ($prefixLength < 0 || $prefixLength > $maxBits) {
        return false;
    }

    $fullBytes = intdiv($prefixLength, 8);
    $remainingBits = $prefixLength % 8;

    /*
     * Compara os bytes completos.
     */
    if ($fullBytes > 0) {

        if (
            substr($ipBinary, 0, $fullBytes) !==
            substr($networkBinary, 0, $fullBytes)
        ) {
            return false;
        }
    }

    /*
     * Se não existirem bits restantes,
     * já podemos considerar válido.
     */
    if ($remainingBits === 0) {
        return true;
    }

    $mask = (0xFF << (8 - $remainingBits)) & 0xFF;

    $ipByte = ord($ipBinary[$fullBytes]);
    $networkByte = ord($networkBinary[$fullBytes]);

    return (($ipByte & $mask) === ($networkByte & $mask));
}


/* ============================================================
 * REDE AUTORIZADA
 * ============================================================ */

function access_network_allowed(string $ip): bool
{
    /*
     * Restrição desativada.
     */
    if (!COMMON_NETWORK_RESTRICTION_ENABLED) {
        return true;
    }

    if ($ip === '') {
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
 * DIA AUTORIZADO
 * ============================================================ */

function access_day_allowed(DateTimeImmutable $now): bool
{
    $day = (int)$now->format('N');

    return in_array(
        $day,
        COMMON_ALLOWED_DAYS,
        true
    );
}


/* ============================================================
 * HORÁRIO AUTORIZADO
 * ============================================================ */

function access_time_allowed(DateTimeImmutable $now): bool
{
    $currentMinutes =
        ((int)$now->format('H') * 60) +
        (int)$now->format('i');

    list($startHour, $startMinute) =
        array_map('intval', explode(':', COMMON_ACCESS_START));

    list($endHour, $endMinute) =
        array_map('intval', explode(':', COMMON_ACCESS_END));

    $startMinutes = ($startHour * 60) + $startMinute;
    $endMinutes   = ($endHour * 60) + $endMinute;


    /*
     * Horário normal:
     *
     * 07:00 até 18:00
     */
    if ($startMinutes <= $endMinutes) {

        return
            $currentMinutes >= $startMinutes &&
            $currentMinutes <= $endMinutes;
    }


    /*
     * Também suporta intervalo atravessando meia-noite.
     *
     * Exemplo:
     * 20:00 até 06:00
     */
    return
        $currentMinutes >= $startMinutes ||
        $currentMinutes <= $endMinutes;
}


/* ============================================================
 * POLÍTICA COMPLETA DO PERFIL COMUM
 * ============================================================ */

/**
 * Retorna:
 *
 * [
 *     'allowed' => true/false,
 *     'reason'  => 'motivo',
 *     'ip'      => 'IP'
 * ]
 */
function access_check_common(): array
{
    $timezone = new DateTimeZone('America/Manaus');

    $now = new DateTimeImmutable(
        'now',
        $timezone
    );

    $ip = access_client_ip();


    /* ================= DIA ================= */

    if (!access_day_allowed($now)) {

        return [
            'allowed' => false,
            'reason'  => 'Acesso não permitido neste dia.',
            'ip'      => $ip
        ];
    }


    /* ================= HORÁRIO ================= */

    if (!access_time_allowed($now)) {

        return [
            'allowed' => false,
            'reason'  => 'Acesso não permitido neste horário.',
            'ip'      => $ip
        ];
    }


    /* ================= REDE ================= */

    if (!access_network_allowed($ip)) {

        return [
            'allowed' => false,
            'reason'  => 'Acesso permitido somente através da rede autorizada.',
            'ip'      => $ip
        ];
    }


    /* ================= TUDO OK ================= */

    return [
        'allowed' => true,
        'reason'  => '',
        'ip'      => $ip
    ];
}

?>
<?php
// config/functions.php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function login_check() {
    if (!isset($_SESSION['user_id']) && !isset($_SESSION['secretaria_id'])) {
        header("Location: login.php");
        exit();
    }
}

function admin_check() {
    $nivel = strtoupper($_SESSION['nivel'] ?? '');
    if ($nivel !== 'ADMIN' && $nivel !== 'SUPORTE') {
        header("Location: dashboard.php?error=access_denied");
        exit();
    }
}

function view_check() {
    $nivel = strtoupper($_SESSION['nivel'] ?? '');
    $allowed = ['ADMIN', 'SUPORTE', 'SECRETARIO', 'CASA_CIVIL', 'SEFAZ'];
    if (!in_array($nivel, $allowed, true)) {
        header("Location: dashboard.php?error=access_denied");
        exit();
    }
}

function casa_civil_check() {
    $nivel = strtoupper($_SESSION['nivel'] ?? '');
    if ($nivel !== 'CASA_CIVIL' && $nivel !== 'ADMIN' && $nivel !== 'SUPORTE') {
        header("Location: dashboard.php?error=access_denied");
        exit();
    }
}

function sefaz_check() {
    $nivel = strtoupper($_SESSION['nivel'] ?? '');
    if ($nivel !== 'SEFAZ' && $nivel !== 'ADMIN' && $nivel !== 'SUPORTE') {
        header("Location: dashboard.php?error=access_denied");
        exit();
    }
}

function suporte_check() {
    $nivel = strtoupper($_SESSION['nivel'] ?? '');
    if ($nivel !== 'SUPORTE') {
        header("Location: dashboard.php?error=access_denied");
        exit();
    }
}

function log_action($pdo, $acao, $detalhes = null) {
    $usuario_id = $_SESSION['user_id'] ?? null;
    $secretaria_id = $_SESSION['secretaria_id'] ?? null;
    $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';

    $stmt = $pdo->prepare("
        INSERT INTO logs (usuario_id, secretaria_id, acao, detalhes, ip)
        VALUES (?, ?, ?, ?, ?)
    ");
    $stmt->execute([$usuario_id, $secretaria_id, $acao, $detalhes, $ip]);
}

function flash_message($type, $msg) {
    $_SESSION['flash'] = [
        'type' => $type,
        'msg'  => $msg
    ];
}

function display_flash() {
    if (isset($_SESSION['flash'])) {
        $f = $_SESSION['flash'];
        $type = htmlspecialchars($f['type'] ?? 'info', ENT_QUOTES, 'UTF-8');
        $msg  = htmlspecialchars($f['msg'] ?? '', ENT_QUOTES, 'UTF-8');

        echo "<div class='alert alert-{$type} no-print'>{$msg}</div>";
        unset($_SESSION['flash']);
    }
}

function format_money($val) {
    return "R$ " . number_format((float)$val, 2, ',', '.');
}

function format_date($date) {
    if (empty($date)) {
        return '';
    }

    $timestamp = strtotime($date);
    if ($timestamp === false) {
        return '';
    }

    return date('d/m/Y H:i', $timestamp);
}

function generate_oficio_number($pdo) {
    $year = date('Y');

    $stmt = $pdo->query("
        SELECT numero
        FROM oficios
        WHERE numero IS NOT NULL
          AND numero <> ''
          AND numero LIKE 'OF-$year-%'
        ORDER BY id DESC
        LIMIT 1
    ");

    $ultimoNumero = $stmt->fetchColumn();

    if (!$ultimoNumero) {
        return "OF-$year-0001";
    }

    if (preg_match('/^OF\-(\d{4})\-(\d+)$/', $ultimoNumero, $matches)) {
        $anoUltimo = $matches[1];
        $sequenciaUltima = (int)$matches[2];

        if ($anoUltimo !== $year) {
            return "OF-$year-0001";
        }

        $novaSequencia = $sequenciaUltima + 1;
        return "OF-$year-" . str_pad((string)$novaSequencia, 4, '0', STR_PAD_LEFT);
    }

    return "OF-$year-0001";
}

function generate_aquisicao_number($pdo) {
    $year = date('Y');

    /*
     * Regras:
     * - NÃO conta quantidade de registros
     * - pega o último numero_aq salvo no banco
     * - se o último for 2026-0026, o próximo será 2026-0027
     * - aceita também formato antigo AQ-2026-0026
     */

    $stmt = $pdo->query("
        SELECT numero_aq
        FROM aquisicoes
        WHERE numero_aq IS NOT NULL
          AND numero_aq <> ''
        ORDER BY id DESC
        LIMIT 1
    ");

    $ultimoNumero = $stmt->fetchColumn();

    if (!$ultimoNumero) {
        return $year . '-0001';
    }

    $ultimoNumero = trim((string)$ultimoNumero);

    // Formato novo: 2026-0026
    if (preg_match('/^(\d{4})-(\d+)$/', $ultimoNumero, $matches)) {
        $anoUltimo = $matches[1];
        $sequenciaUltima = (int)$matches[2];

        if ($anoUltimo !== $year) {
            return $year . '-0001';
        }

        $novaSequencia = $sequenciaUltima + 1;
        return $year . '-' . str_pad((string)$novaSequencia, 4, '0', STR_PAD_LEFT);
    }

    // Formato antigo: AQ-2026-0026
    if (preg_match('/^AQ-(\d{4})-(\d+)$/', $ultimoNumero, $matches)) {
        $anoUltimo = $matches[1];
        $sequenciaUltima = (int)$matches[2];

        if ($anoUltimo !== $year) {
            return $year . '-0001';
        }

        $novaSequencia = $sequenciaUltima + 1;
        return $year . '-' . str_pad((string)$novaSequencia, 4, '0', STR_PAD_LEFT);
    }

    // Se estiver fora do padrão, reinicia com segurança
    return $year . '-0001';
}

function generate_unique_code($pdo) {
    $year = date('Y');

    do {
        $code = "ENT-$year-" . strtoupper(substr(md5(uniqid((string)mt_rand(), true)), 0, 5));

        $stmt = $pdo->prepare("SELECT id FROM aquisicoes WHERE codigo_entrega = ?");
        $stmt->execute([$code]);

    } while ($stmt->fetch());

    return $code;
}
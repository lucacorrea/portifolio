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
    if (!in_array($nivel, $allowed)) {
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
    global $pdo;
    $usuario_id = $_SESSION['user_id'] ?? null;
    $secretaria_id = $_SESSION['secretaria_id'] ?? null;
    $ip = $_SERVER['REMOTE_ADDR'];

    $stmt = $pdo->prepare("INSERT INTO logs (usuario_id, secretaria_id, acao, detalhes, ip) VALUES (?, ?, ?, ?, ?)");
    $stmt->execute([$usuario_id, $secretaria_id, $acao, $detalhes, $ip]);
}

function flash_message($type, $msg) {
    $_SESSION['flash'] = ['type' => $type, 'msg' => $msg];
}

function display_flash() {
    if (isset($_SESSION['flash'])) {
        $f = $_SESSION['flash'];
        echo "<div class='alert alert-{$f['type']} no-print'>{$f['msg']}</div>";
        unset($_SESSION['flash']);
    }
}

function format_money($val) {
    return "R$ " . number_format($val, 2, ',', '.');
}

function format_date($date) {
    return date('d/m/Y H:i', strtotime($date));
}

function generate_oficio_number($pdo) {
    $year = date('Y');
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM oficios WHERE numero LIKE 'OF-$year-%'");
    $count = $stmt->fetch()['total'] + 1;
    return "OF-$year-" . str_pad($count, 4, '0', STR_PAD_LEFT);
}

function generate_aquisicao_number($pdo) {
    $year = date('Y');
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM aquisicoes WHERE numero_aq LIKE 'AQ-$year-%'");
    $count = $stmt->fetch()['total'] + 1;
    return "AQ-$year-" . str_pad($count, 4, '0', STR_PAD_LEFT);
}

function generate_unique_code($pdo) {
    $year = date('Y');
    do {
        $code = "ENT-$year-" . strtoupper(substr(md5(uniqid(mt_rand(), true)), 0, 5));
        $stmt = $pdo->prepare("SELECT id FROM aquisicoes WHERE codigo_entrega = ?");
        $stmt->execute([$code]);
    } while ($stmt->fetch());
    return $code;
}

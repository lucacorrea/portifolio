<?php
/**
 * Global Helper Functions
 */

if (!function_exists('formatarMoeda')) {
    function formatarMoeda($valor) {
        return 'R$ ' . number_format($valor, 2, ',', '.');
    }
}

if (!function_exists('formatarData')) {
    function formatarData($data) {
        if (!$data) return '-';
        return date('d/m/Y', strtotime($data));
    }
}

if (!function_exists('formatarDataHora')) {
    function formatarDataHora($data) {
        if (!$data) return '-';
        return date('d/m/Y H:i', strtotime($data));
    }
}

if (!function_exists('getStatusColor')) {
    function getStatusColor($status) {
        $colors = [
            'orcamento'      => '#6c757d', // Secondary
            'aprovado'       => '#0dcaf0', // Info
            'em_andamento'   => '#0d6efd', // Primary
            'aguardando_peca' => '#6610f2', // Purple
            'concluido'      => '#198754', // Success
            'entregue'       => '#20c997', // Teal
            'cancelado'      => '#dc3545', // Danger
            'pendente'       => '#ffc107', // Warning
            'pago'           => '#198754', // Success
            'atrasado'       => '#dc3545'  // Danger
        ];
        return $colors[$status] ?? '#6c757d';
    }
}

if (!function_exists('setFlash')) {
    function setFlash($type, $message) {
        $_SESSION['flash'] = [
            'type' => $type,
            'message' => $message
        ];
    }
}

if (!function_exists('getFlash')) {
    function getFlash() {
        if (isset($_SESSION['flash'])) {
            $flash = $_SESSION['flash'];
            unset($_SESSION['flash']);
            return $flash;
        }
        return null;
    }
}

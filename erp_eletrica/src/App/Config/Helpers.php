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
if (!function_exists('jsonResponse')) {
    function jsonResponse($success, $message, $data = [], $code = 200) {
        if (!headers_sent()) {
            http_response_code($code);
            header('Content-Type: application/json');
        }
        echo json_encode([
            'success' => $success,
            'message' => $message,
            'data'    => $data,
            'timestamp' => date('Y-m-d H:i:s')
        ]);
        exit;
    }
}
if (!function_exists('renderPagination')) {
    function renderPagination($pagination, $baseUrl, $queryParams = []) {
        if (!isset($pagination['pages']) || $pagination['pages'] <= 1) return '';

        $current = (int)$pagination['current'];
        $total = (int)$pagination['pages'];
        
        // Build query string excluding 'page'
        unset($queryParams['page']);
        $queryString = http_build_query($queryParams);
        $urlWithParams = $baseUrl . (str_contains($baseUrl, '?') ? '&' : '?') . $queryString;
        if (!empty($queryString)) $urlWithParams .= '&';

        $html = '<nav aria-label="Navegação" class="d-flex flex-column flex-md-row align-items-center justify-content-center gap-3">';
        $html .= '<ul class="pagination pagination-sm mb-0">';
        
        // Previous
        $prevDisabled = $current <= 1 ? 'disabled' : '';
        $html .= "<li class=\"page-item $prevDisabled\"><a class=\"page-link\" href=\"{$urlWithParams}page=" . ($current - 1) . "\"><i class=\"fas fa-chevron-left\"></i></a></li>";

        // Logic for smart numbering
        $links = [];
        $links[] = 1; // Always show first

        if ($current > 4) {
            $links[] = '...';
        }

        for ($i = max(2, $current - 2); $i <= min($total - 1, $current + 2); $i++) {
            $links[] = $i;
        }

        if ($current < $total - 3) {
            $links[] = '...';
        }

        if ($total > 1) {
            $links[] = $total; // Always show last
        }

        foreach ($links as $link) {
            if ($link === '...') {
                $html .= '<li class="page-item disabled"><span class="page-link border-0">...</span></li>';
            } else {
                $active = $link == $current ? 'active' : '';
                $html .= "<li class=\"page-item $active\"><a class=\"page-link\" href=\"{$urlWithParams}page=$link\">$link</a></li>";
            }
        }

        // Next
        $nextDisabled = $current >= $total ? 'disabled' : '';
        $html .= "<li class=\"page-item $nextDisabled\"><a class=\"page-link\" href=\"{$urlWithParams}page=" . ($current + 1) . "\"><i class=\"fas fa-chevron-right\"></i></a></li>";
        
        $html .= '</ul>';

        // Go to Page Input
        $html .= '<div class="d-flex align-items-center gap-2">';
        $html .= '<span class="text-muted small text-nowrap">Ir para:</span>';
        $html .= "<input type=\"number\" class=\"form-control form-control-sm text-center\" style=\"width: 60px;\" min=\"1\" max=\"$total\" value=\"$current\" onkeydown=\"if(event.key==='Enter') window.location.href='{$urlWithParams}page='+this.value\">";
        $html .= "<button class=\"btn btn-sm btn-outline-secondary\" onclick=\"window.location.href='{$urlWithParams}page='+this.previousElementSibling.value\"><i class=\"fas fa-arrow-right\"></i></button>";
        $html .= '</div>';

        $html .= '</nav>';

        return $html;
    }
}

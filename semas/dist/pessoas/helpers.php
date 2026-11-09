<?php

declare(strict_types=1);

if (!function_exists('pc_h')) {
    function pc_h($value)
    {
        return htmlspecialchars((string)($value === null ? '' : $value), ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('pc_only_digits')) {
    function pc_only_digits($value)
    {
        return preg_replace('/\D+/', '', (string)$value) ?: '';
    }
}

if (!function_exists('pc_cpf')) {
    function pc_cpf($value)
    {
        $digits = pc_only_digits($value);
        if (strlen($digits) !== 11) {
            return $value ? (string)$value : '—';
        }
        return substr($digits, 0, 3) . '.' . substr($digits, 3, 3) . '.' . substr($digits, 6, 3) . '-' . substr($digits, 9, 2);
    }
}

if (!function_exists('pc_phone')) {
    function pc_phone($value)
    {
        $digits = pc_only_digits($value);
        if (strlen($digits) === 11) {
            return '(' . substr($digits, 0, 2) . ') ' . substr($digits, 2, 5) . '-' . substr($digits, 7, 4);
        }
        if (strlen($digits) === 10) {
            return '(' . substr($digits, 0, 2) . ') ' . substr($digits, 2, 4) . '-' . substr($digits, 6, 4);
        }
        return $value ? (string)$value : '—';
    }
}

if (!function_exists('pc_money')) {
    function pc_money($value)
    {
        if ($value === null || $value === '') {
            return '—';
        }
        return 'R$ ' . number_format((float)$value, 2, ',', '.');
    }
}

if (!function_exists('pc_date')) {
    function pc_date($value, $withTime = false)
    {
        if (!$value || $value === '0000-00-00' || $value === '0000-00-00 00:00:00') {
            return '—';
        }
        $timestamp = strtotime((string)$value);
        if (!$timestamp) {
            return (string)$value;
        }
        return date($withTime ? 'd/m/Y H:i' : 'd/m/Y', $timestamp);
    }
}

if (!function_exists('pc_lower')) {
    function pc_lower($value)
    {
        $value = (string)$value;
        return function_exists('mb_strtolower') ? mb_strtolower($value, 'UTF-8') : strtolower($value);
    }
}

if (!function_exists('pc_initial')) {
    function pc_initial($value)
    {
        $value = trim((string)$value);
        if ($value === '') return '?';
        $char = function_exists('mb_substr') ? mb_substr($value, 0, 1, 'UTF-8') : substr($value, 0, 1);
        return function_exists('mb_strtoupper') ? mb_strtoupper($char, 'UTF-8') : strtoupper($char);
    }
}

if (!function_exists('pc_photo_url')) {
    /**
     * Resolve a foto pela MESMA infraestrutura usada por editarSolicitante.php.
     *
     * O sistema atual pode armazenar os arquivos fora do public_html
     * (ex.: /home/.../semas_uploads/fotos). Por isso não devemos montar
     * /dist/uploads/fotos/... manualmente.
     *
     * semas_storage_absolute_path() localiza o arquivo físico considerando:
     * - armazenamento externo atual;
     * - pasta irmã "uploads";
     * - diretórios "fotos" e "fotos v1";
     * - legado em /dist/uploads.
     *
     * semas_storage_public_url() devolve:
     * arquivo.php?path=uploads%2Ffotos%2Farquivo.jpg
     *
     * que é exatamente o mecanismo usado no Editar Solicitante.
     */
    function pc_photo_url($value, $fallback = 'assets/images/user.png')
    {
        $path = trim((string)$value);
        if ($path === '') {
            return $fallback;
        }

        if (
            function_exists('semas_storage_absolute_path')
            && function_exists('semas_storage_public_url')
            && semas_storage_absolute_path($path) !== ''
        ) {
            $url = semas_storage_public_url($path);
            return $url !== '' ? $url : $fallback;
        }

        return $fallback;
    }
}

if (!function_exists('pc_has_photo')) {
    function pc_has_photo($value)
    {
        $path = trim((string)$value);
        if ($path === '') {
            return false;
        }

        return function_exists('semas_storage_absolute_path')
            && semas_storage_absolute_path($path) !== '';
    }
}

if (!function_exists('pc_json')) {
    function pc_json($data)
    {
        return json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
}

if (!function_exists('pc_table_has_column')) {
    function pc_table_has_column(PDO $pdo, $table, $column)
    {
        static $cache = array();
        $key = $table . ':' . $column;
        if (array_key_exists($key, $cache)) {
            return $cache[$key];
        }
        $stmt = $pdo->prepare('SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :t AND COLUMN_NAME = :c');
        $stmt->execute(array(':t' => $table, ':c' => $column));
        $cache[$key] = ((int)$stmt->fetchColumn() > 0);
        return $cache[$key];
    }
}

if (!function_exists('pc_session_csrf')) {
    function pc_session_csrf()
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }
        if (empty($_SESSION['pc_csrf'])) {
            try {
                $_SESSION['pc_csrf'] = bin2hex(random_bytes(32));
            } catch (Exception $e) {
                $_SESSION['pc_csrf'] = hash('sha256', uniqid('', true) . mt_rand());
            }
        }
        return (string)$_SESSION['pc_csrf'];
    }
}

if (!function_exists('pc_verify_csrf')) {
    function pc_verify_csrf($token)
    {
        $expected = isset($_SESSION['pc_csrf']) ? (string)$_SESSION['pc_csrf'] : '';
        return $expected !== '' && is_string($token) && hash_equals($expected, $token);
    }
}

if (!function_exists('pc_responsavel_expr')) {
    function pc_responsavel_expr(PDO $pdo)
    {
        /* Mesma detecção tolerante usada pela página antiga. */
        $columns = array(
            'responsavel',
            'responsavel_cadastro',
            'servidor',
            'servidor_cadastro',
            'usuario_responsavel',
            'usuario_cadastro',
            'criado_por',
            'created_by',
            'usuario_nome'
        );
        foreach ($columns as $column) {
            if (pc_table_has_column($pdo, 'solicitantes', $column)) {
                return "COALESCE(NULLIF(TRIM(s.`" . $column . "`),''), '—')";
            }
        }
        return "'—'";
    }
}

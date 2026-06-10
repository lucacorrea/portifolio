<?php
declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/config/conexao.php';

function db(): PDO
{
    return gestao_pdo();
}

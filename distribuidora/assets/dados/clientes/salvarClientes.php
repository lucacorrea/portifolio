<?php
declare(strict_types=1);

@date_default_timezone_set('America/Manaus');
if (session_status() !== PHP_SESSION_ACTIVE) session_start();

require_once __DIR__ . '/../../conexao.php';
require_once __DIR__ . '/_helpers.php';

require_db_or_die();
$pdo = db();

/**
 * URL padrão /distribuidora/clientes.php (sem ../../../)
 */
$script = str_replace('\\', '/', (string)($_SERVER['SCRIPT_NAME'] ?? ''));
$root = preg_replace('#/assets/dados/clientes/[^/]+$#', '', $script);
$clientesUrl = rtrim($root ?: '/', '/') . '/clientes.php';

if (!is_post()) redirect($clientesUrl);

csrf_validate_or_die();
$return = safe_return_to(post_str('return_to', $clientesUrl));

/* ========= DADOS ========= */
$nome = trim(post_str('nome'));

// CPF: pode vir com pontos e traço -> salva só números
$cpfDigits = only_digits(post_str('cpf'));

// Telefone: PODE salvar formatado (como digitado)
$telefoneInput = trim(post_str('telefone'));      // salva assim
$telefoneDigits = only_digits($telefoneInput);    // usa só pra validar

$end  = trim(post_str('endereco', ''));

/* ========= VALIDAÇÕES ========= */
if ($nome === '' || mb_strlen($nome) < 2) {
  flash_set('flash_err', 'Informe um nome válido.');
  redirect($return);
}

// CPF: valida só 11 dígitos (sem dígito verificador)
if (strlen($cpfDigits) !== 11 || preg_match('/^(\d)\1{10}$/', $cpfDigits)) {
  flash_set('flash_err', 'CPF deve ter 11 dígitos (somente números).');
  redirect($return);
}

// Telefone: valida pelo mínimo de dígitos, mas salva formatado
if (!tel_min_ok($telefoneDigits)) {
  flash_set('flash_err', 'Telefone inválido.');
  redirect($return);
}

try {
  // CPF único (robusto: pega casos antigos com pontuação)
  $st = $pdo->prepare("
    SELECT id
    FROM clientes
    WHERE REPLACE(REPLACE(REPLACE(cpf,'.',''),'-',''),' ','') = :cpf
    LIMIT 1
  ");
  $st->execute(['cpf' => $cpfDigits]);
  if ($st->fetchColumn()) {
    flash_set('flash_err', 'CPF já cadastrado.');
    redirect($return);
  }

  $sql = "INSERT INTO clientes (nome, cpf, telefone, endereco, created_at, updated_at)
          VALUES (:nome, :cpf, :tel, :end, NOW(), NOW())";
  $stmt = $pdo->prepare($sql);
  $stmt->execute([
    'nome' => $nome,
    'cpf'  => $cpfDigits,                 // <<< CPF vai SEM . e -
    'tel'  => $telefoneInput,             // <<< TELEFONE pode ir formatado
    'end'  => ($end !== '' ? $end : null),
  ]);

  flash_set('flash_ok', 'Cliente cadastrado com sucesso.');
  redirect($return);
} catch (Throwable $e) {
  flash_set('flash_err', 'Erro ao salvar cliente: ' . $e->getMessage());
  redirect($return);
}

?>
<?php
declare(strict_types=1);
require_once __DIR__ . '/auth_guard_real_admin.php';

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new RuntimeException('Método inválido.');
    }

    if (!csrf_check($_POST['csrf'] ?? null)) {
        throw new RuntimeException('Token inválido.');
    }

    $nome = trim((string)($_POST['nome'] ?? ''));
    $email = trim((string)($_POST['email'] ?? ''));
    $nivel = trim((string)($_POST['nivel'] ?? ''));
    $filialId = trim((string)($_POST['filial_id'] ?? ''));
    $descontoMaximo = (float)($_POST['desconto_maximo'] ?? 0);

    $niveisPermitidos = ['vendedor', 'tecnico', 'gerente', 'admin'];

    if ($nome === '' || $email === '' || $nivel === '') {
        throw new RuntimeException('Preencha nome, e-mail e tipo de usuário.');
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        throw new RuntimeException('E-mail inválido.');
    }

    if (!in_array($nivel, $niveisPermitidos, true)) {
        throw new RuntimeException('Tipo de usuário inválido.');
    }

    $codigo = random_temp_code(8);
    $codigoHash = password_hash($codigo, PASSWORD_DEFAULT);
    $senhaFake = password_hash(bin2hex(random_bytes(16)), PASSWORD_DEFAULT);
    $expiraEm = date('Y-m-d H:i:s', time() + (30 * 60));

    $stmt = db()->prepare("SELECT * FROM usuarios WHERE email = :email LIMIT 1");
    $stmt->execute([':email' => $email]);
    $existente = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($existente && (int)$existente['is_temp_admin'] !== 1) {
        throw new RuntimeException('Já existe um usuário fixo com este e-mail.');
    }

    if ($existente && (int)$existente['is_temp_admin'] === 1) {
        $stmt = db()->prepare("
            UPDATE usuarios
               SET filial_id = :filial_id,
                   nome = :nome,
                   senha = :senha,
                   nivel = :nivel,
                   ativo = 1,
                   auth_pin = :auth_pin,
                   auth_type = 'pin',
                   desconto_maximo = :desconto_maximo,
                   is_temp_admin = 1,
                   temp_admin_expires_at = :expira_em,
                   temp_admin_created_by = :created_by,
                   passkey_enabled = 0,
                   passkey_user_handle = NULL
             WHERE id = :id
        ");

        $stmt->execute([
            ':filial_id'       => $filialId !== '' ? (int)$filialId : null,
            ':nome'            => $nome,
            ':senha'           => $senhaFake,
            ':nivel'           => $nivel,
            ':auth_pin'        => $codigoHash,
            ':desconto_maximo' => $descontoMaximo,
            ':expira_em'       => $expiraEm,
            ':created_by'      => (int)$usuarioLogado['id'],
            ':id'              => (int)$existente['id'],
        ]);

        $userId = (int)$existente['id'];
    } else {
        $stmt = db()->prepare("
            INSERT INTO usuarios (
                filial_id, nome, email, senha, nivel, avatar, ativo, last_login,
                auth_pin, auth_type, desconto_maximo, is_temp_admin,
                temp_admin_expires_at, temp_admin_created_by,
                passkey_enabled, passkey_user_handle
            ) VALUES (
                :filial_id, :nome, :email, :senha, :nivel, 'default_avatar.png', 1, NULL,
                :auth_pin, 'pin', :desconto_maximo, 1,
                :expira_em, :created_by,
                0, NULL
            )
        ");

        $stmt->execute([
            ':filial_id'       => $filialId !== '' ? (int)$filialId : null,
            ':nome'            => $nome,
            ':email'           => $email,
            ':senha'           => $senhaFake,
            ':nivel'           => $nivel,
            ':auth_pin'        => $codigoHash,
            ':desconto_maximo' => $descontoMaximo,
            ':expira_em'       => $expiraEm,
            ':created_by'      => (int)$usuarioLogado['id'],
        ]);

        $userId = (int)db()->lastInsertId();
    }

    $_SESSION['generated_temp_user'] = [
        'id'       => $userId,
        'codigo'   => $codigo,
        'email'    => $email,
        'nivel'    => $nivel,
        'expira_em'=> date('d/m/Y H:i', strtotime($expiraEm)),
    ];

    flash('ok', 'Usuário temporário gerado com sucesso.');
    redirect('painel_admin.php');
} catch (Throwable $e) {
    flash('erro', $e->getMessage());
    redirect('painel_admin.php');
}

?>
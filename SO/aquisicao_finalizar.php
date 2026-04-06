<?php
require_once 'config/database.php';
require_once 'config/functions.php';
login_check();

$id = $_GET['id'] ?? 0;

if ($id > 0) {
    try {
        $stmt = $pdo->prepare("SELECT * FROM aquisicoes WHERE id = ?");
        $stmt->execute([$id]);
        $aq = $stmt->fetch();

        if ($aq) {
            if ($aq['status'] === 'FINALIZADO') {
                flash_message('warning', "Esta aquisição já foi finalizada anteriormente.");
            } else {
                $stmt_upd = $pdo->prepare("UPDATE aquisicoes SET status = 'FINALIZADO', data_finalizacao = CURRENT_TIMESTAMP, usuario_id_finalizou = ? WHERE id = ?");
                $stmt_upd->execute([$_SESSION['user_id'], $id]);
                
                log_action($pdo, "FINALIZAR_AQUISICAO", "Aquisição {$aq['numero_aq']} finalizada manualmente pelo ADMIN");
                flash_message('success', "Aquisição {$aq['numero_aq']} marcada como RECEBIDA com sucesso!");
            }
        } else {
            flash_message('danger', "Aquisição não encontrada.");
        }
    } catch (Exception $e) {
        flash_message('danger', "Erro ao finalizar: " . $e->getMessage());
    }
}

header("Location: aquisicoes_lista.php");
exit();
